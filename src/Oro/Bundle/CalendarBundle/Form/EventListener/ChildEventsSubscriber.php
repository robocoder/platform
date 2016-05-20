<?php

namespace Oro\Bundle\CalendarBundle\Form\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Oro\Bundle\CalendarBundle\Entity\Attendee;
use Oro\Bundle\CalendarBundle\Entity\CalendarEvent;
use Oro\Bundle\CalendarBundle\Entity\Repository\CalendarRepository;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Component\PhpUtils\ArrayUtil;

class ChildEventsSubscriber implements EventSubscriberInterface
{
    /** @var ManagerRegistry */
    protected $registry;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var CalendarEvent */
    protected $parentEvent;

    /**
     * @param FormBuilderInterface $builder
     * @param ManagerRegistry $registry
     * @param SecurityFacade $securityFacade
     * @param string $childEventsFieldName
     */
    public function __construct(
        FormBuilderInterface $builder,
        ManagerRegistry $registry,
        SecurityFacade $securityFacade,
        $childEventsFieldName = 'attendees'
    ) {
        $this->registry= $registry;
        $this->securityFacade = $securityFacade;

        // get existing events
        $builder->get($childEventsFieldName)
            ->addEventListener(FormEvents::POST_SUBMIT, [$this, 'postSubmitChildEvents']);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SUBMIT => 'preSubmit', // extract master event
            FormEvents::POST_SUBMIT => 'postSubmit', // synchronize child events
        ];
    }

    /**
     * PRE_SUBMIT event handler
     *
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $data = $event->getForm()->getData();
        if ($data) {
            $this->parentEvent = $data;
        }
    }

    /**
     * POST_SUBMIT event handler
     *
     * @param FormEvent $event
     */
    public function postSubmitChildEvents(FormEvent $event)
    {
        /** @var Attendee[] $attendees */
        $attendees = $event->getForm()->getData();
        if ($attendees && $this->parentEvent) {
            $existingAttendees = $this->parentEvent->getAttendees();
            foreach ($attendees as $key => $attendee) {
                $existingAttendee = ArrayUtil::find(
                    function (Attendee $existingAttendee) use ($attendee) {
                        if ($attendee->getUser()) {
                            return $existingAttendee->getUser() &&
                                $existingAttendee->getUser()->getId() === $attendee->getUser()->getId();
                        }

                        return !$existingAttendee->getUser() && $existingAttendee->getEmail() === $attendee->getEmail();
                    },
                    $existingAttendees->toArray()
                );

                if (!$existingAttendee) {
                    continue;
                }

                $attendees[$key] = $existingAttendee;
            }
        }
    }

    /**
     * POST_SUBMIT event handler
     *
     * @param FormEvent $event
     */
    public function postSubmit(FormEvent $event)
    {
        /** @var CalendarEvent $parentEvent */
        $parentEvent = $event->getForm()->getData();
        $this->updateCalendarEvents($parentEvent);
        $this->updateAttendeeDisplayNames($parentEvent);
        if (!$parentEvent) {
            return;
        }

        $this->setDefaultAttendeeStatus($parentEvent->getRelatedAttendee(), CalendarEvent::STATUS_ACCEPTED);
        foreach ($parentEvent->getChildEvents() as $calendarEvent) {
            $calendarEvent
                ->setTitle($parentEvent->getTitle())
                ->setDescription($parentEvent->getDescription())
                ->setStart($parentEvent->getStart())
                ->setEnd($parentEvent->getEnd())
                ->setAllDay($parentEvent->getAllDay());
        }

        foreach ($parentEvent->getChildAttendees() as $attendee) {
            $this->setDefaultAttendeeStatus($attendee);
        }
    }

    /**
     * @param CalendarEvent $parent
     */
    protected function updateCalendarEvents(CalendarEvent $parent)
    {
        /** @var CalendarRepository $calendarRepository */
        $calendarRepository = $this->registry->getRepository('OroCalendarBundle:Calendar');
        $organizationId = $this->securityFacade->getOrganizationId();

        $attendeesByUserId = [];
        $attendees = $parent->getAttendees();
        foreach ($attendees as $attendee) {
            if (!$attendee->getUser()) {
                continue;
            }

            $attendeesByUserId[$attendee->getUser()->getId()] = $attendee;
        }
        $currentUserIds = array_keys($attendeesByUserId);

        $calendarEventOwnerIds = [];
        $calendar = $parent->getCalendar();
        if ($calendar && $calendar->getOwner()) {
            $owner = $calendar->getOwner();
            if (isset($attendeesByUserId[$owner->getId()])) {
                $parent->setRelatedAttendee($attendeesByUserId[$owner->getId()]);
            }
            $calendarEventOwnerIds[] = $calendar->getOwner()->getId();
        }
        $events = $parent->getChildEvents();
        foreach ($events as $event) {
            $calendar = $event->getCalendar();
            if (!$calendar) {
                continue;
            }

            $owner = $calendar->getOwner();
            if (!$owner) {
                continue;
            }

            $ownerId = $owner->getId();
            if (!in_array($ownerId, $currentUserIds)) {
                $parent->removeChildEvent($event);

                continue;
            }

            $calendarEventOwnerIds[] = $ownerId;
        }

        $missingEventUserIds = array_diff($currentUserIds, $calendarEventOwnerIds);
        if ($missingEventUserIds) {
            $calendars = $calendarRepository->findDefaultCalendars($missingEventUserIds, $organizationId);
            foreach ($calendars as $calendar) {
                $event = new CalendarEvent();
                $event->setCalendar($calendar);
                $parent->addChildEvent($event);
                if ($calendar->getOwner() && isset($attendeesByUserId[$calendar->getOwner()->getId()])) {
                    $event->setRelatedAttendee($attendeesByUserId[$calendar->getOwner()->getId()]);
                }
            }
        }
    }

    /**
     * @param CalendarEvent $parent
     */
    protected function updateAttendeeDisplayNames(CalendarEvent $parent)
    {
        foreach ($parent->getAttendees() as $attendee) {
            if ($attendee->getDisplayName()) {
                continue;
            }

            $displayName = $attendee->getUser()
                ? $attendee->getUser()->getFullName()
                : $attendee->getEmail();
            $attendee->setDisplayName($displayName);
        }
    }

    /**
     * @param Attendee|null $attendee
     * @param string        $status
     */
    protected function setDefaultAttendeeStatus(
        Attendee $attendee = null,
        $status = CalendarEvent::STATUS_NOT_RESPONDED
    ) {
        if (!$attendee || $attendee->getStatus()) {
            return;
        }

        $statusEnum = $this->registry
            ->getRepository(ExtendHelper::buildEnumValueClassName(Attendee::STATUS_ENUM_CODE))
            ->find($status);
        $attendee->setStatus($statusEnum);
    }
}

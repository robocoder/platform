<?php

namespace Oro\Bundle\FormBundle\Twig;

use Oro\Bundle\FormBundle\Form\Twig\DataBlockRenderer;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TwigFunction;

/**
 * Provides Twig functions for form rendering:
 *   - form_data_blocks
 *   - oro_form_js_validation
 *   - form_javascript
 *   - form_stylesheet
 */
class FormExtension extends AbstractExtension
{
    const DEFAULT_TEMPLATE = 'OroFormBundle:Form:fields.html.twig';
    const BLOCK_NAME       = 'oro_form_js_validation';

    /**
     * @var FormRendererInterface
     */
    public $renderer;

    /**
     * @var string
     */
    protected $templateName;

    /**
     * @var array
     */
    protected $defaultOptions;

    /**
     * @param FormRendererInterface $renderer
     * @param string $templateName
     * @param array $defaultOptions
     */
    public function __construct(
        FormRendererInterface $renderer,
        $templateName = self::DEFAULT_TEMPLATE,
        $defaultOptions = []
    ) {
        $this->renderer = $renderer;
        $this->templateName = $templateName;
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * @return DataBlockRenderer
     */
    protected function getDataBlockRenderer()
    {
        return new DataBlockRenderer();
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new TwigFunction(
                'form_data_blocks',
                [$this, 'renderFormDataBlocks'],
                ['needs_context' => true, 'needs_environment' => true]
            ),
            new TwigFunction(
                'oro_form_js_validation',
                [$this, 'renderFormJsValidationBlock'],
                ['needs_environment' => true, 'is_safe' => ['html']]
            ),
            new TwigFunction(
                'form_javascript',
                [$this, 'renderJavascript'],
                ['is_safe' => ['html']]
            ),
            new TwigFunction(
                'form_stylesheet',
                null,
                [
                    'is_safe' => ['html'],
                    'node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode',
                ]
            )
        ];
    }

    /**
     * @param Environment       $env
     * @param array             $context
     * @param FormView          $form
     * @param string            $formVariableName
     *
     * @return array
     */
    public function renderFormDataBlocks(Environment $env, $context, FormView $form, $formVariableName = 'form')
    {
        return $this->getDataBlockRenderer()->render($env, $context, $form, $formVariableName);
    }

    /**
     * Renders "oro_form_js_validation" block with init script for JS validation of form.
     *
     * @param Environment       $environment
     * @param FormView          $view
     * @param array             $options
     *
     * @return string
     * @throws \RuntimeException
     */
    public function renderFormJsValidationBlock(Environment $environment, FormView $view, $options = [])
    {
        $options = array_merge($this->defaultOptions, $options);

        /** @var Template $template */
        $template = $environment->loadTemplate($this->templateName);
        if (!$template->hasBlock(self::BLOCK_NAME, [])) {
            throw new \RuntimeException(
                sprintf('Block "%s" is not found in template "%s".', self::BLOCK_NAME, $this->templateName)
            );
        }

        return $template->renderBlock(
            self::BLOCK_NAME,
            [
                'form'       => $view,
                'options'    => $options,
                'js_options' => $this->filterJsOptions($options)
            ]
        );
    }

    /**
     * Render Function Form Javascript
     *
     * @param FormView $view
     * @param bool $prototype
     *
     * @return string
     */
    public function renderJavascript(FormView $view, $prototype = false)
    {
        $block = $prototype ? 'javascript_prototype' : 'javascript';

        return $this->renderer->searchAndRenderBlock($view, $block);
    }

    /**
     * Exclude object values.
     *
     * @param array $options
     *
     * @return array
     */
    protected function filterJsOptions(array $options)
    {
        foreach ($options as $name => $value) {
            if (is_object($value)) {
                unset($options[$name]);
            }
            if (is_array($value)) {
                $options[$name] = $this->filterJsOptions($value);
            }
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_form';
    }
}

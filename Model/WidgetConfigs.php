<?php

namespace Oro\Bundle\DashboardBundle\Model;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\DashboardBundle\Form\Type\WidgetItemsChoiceType;
use Oro\Bundle\DashboardBundle\Entity\Widget;
use Oro\Bundle\DashboardBundle\Provider\ConfigValueProvider;
use Oro\Bundle\SecurityBundle\SecurityFacade;

use Oro\Component\Config\Resolver\ResolverInterface;

class WidgetConfigs
{
    /** @var ConfigProvider */
    protected $configProvider;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var ResolverInterface */
    protected $resolver;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var Request|null */
    protected $request;

    /** @var ConfigValueProvider */
    protected $valueProvider;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param ConfigProvider         $configProvider
     * @param SecurityFacade         $securityFacade
     * @param ResolverInterface      $resolver
     * @param EntityManagerInterface $entityManager
     * @param ConfigValueProvider    $valueProvider
     * @param TranslatorInterface    $translator
     */
    public function __construct(
        ConfigProvider $configProvider,
        SecurityFacade $securityFacade,
        ResolverInterface $resolver,
        EntityManagerInterface $entityManager,
        ConfigValueProvider $valueProvider,
        TranslatorInterface $translator
    ) {
        $this->configProvider = $configProvider;
        $this->securityFacade = $securityFacade;
        $this->resolver = $resolver;
        $this->entityManager = $entityManager;
        $this->valueProvider = $valueProvider;
        $this->translator = $translator;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Returns widget attributes with attribute name converted to use in widget's TWIG template
     *
     * @param string $widgetName The name of widget
     *
     * @return array
     */
    public function getWidgetAttributesForTwig($widgetName)
    {
        $result = [
            'widgetName' => $widgetName
        ];

        $widget = $this->configProvider->getWidgetConfig($widgetName);
        unset($widget['route']);
        unset($widget['route_parameters']);
        unset($widget['acl']);
        unset($widget['items']);

        $options = $widget['configuration'];
        foreach ($options as $name => $config) {
            $widget['configuration'][$name]['value'] = $this->valueProvider->getViewValue(
                $config['type'],
                $this->getWidgetOptions()->get($name)
            );
        }

        foreach ($widget as $key => $val) {
            $attrName = 'widget';
            foreach (explode('_', str_replace('-', '_', $key)) as $keyPart) {
                $attrName .= ucfirst($keyPart);
            }
            $result[$attrName] = $val;
        }

        return $result;
    }

    /**
     * Returns filtered list of widget configuration
     * based on applicable flags and acl
     *
     * @return array
     */
    public function getWidgetConfigs()
    {
        return $this->filterWidgets($this->configProvider->getWidgetConfigs());
    }

    /**
     * Returns a list of items for the given widget
     *
     * @param string $widgetName The name of widget
     *
     * @return array
     */
    public function getWidgetItems($widgetName)
    {
        $widgetConfig = $this->configProvider->getWidgetConfig($widgetName);

        $items = isset($widgetConfig['items']) ? $widgetConfig['items'] : [];
        $items = $this->filterWidgets($items);

        return $items;
    }

    /**
     * @param $widgetName
     * @param $widgetId
     * @return array
     */
    public function getWidgetItemsData($widgetName, $widgetId)
    {
        $widgetConfig  = $this->configProvider->getWidgetConfig($widgetName);
        $widgetOptions = $this->getWidgetOptions($widgetId);

        $items = isset($widgetConfig['data_items']) ? $widgetConfig['data_items'] : [];

        $applyVisible = $this->shouldVisibleItemsBeChecked($widgetConfig);
        $visibleItems = $widgetOptions->get('subWidgets') ?: [];
        $items = $this->filterWidgets($items, $applyVisible, $visibleItems);

        foreach ($items as $itemName => $config) {
            $items[$itemName]['value'] = $this->resolver->resolve(
                [$config['data_provider']],
                ['widgetOptions' => $widgetOptions]
            )[0];
        }

        return $items;
    }

    /**
     * @param array $widgetConfig
     *
     * @return boolean
     */
    private function shouldVisibleItemsBeChecked(array $widgetConfig = [])
    {
        if (!isset($widgetConfig['configuration'], $widgetConfig['configuration']['subWidgets'])) {
            return false;
        }

        return $widgetConfig['configuration']['subWidgets']['type'] === 'oro_type_widget_items_choice';
    }

    /**
     * Returns a list of options for widget with id $widgetId or current widget if $widgetId is not specified
     *
     * @param int|null $widgetId
     *
     * @return WidgetOptionBag
     */
    public function getWidgetOptions($widgetId = null)
    {
        if (!$this->request) {
            return new WidgetOptionBag();
        }

        if (!$widgetId) {
            $widgetId = $this->request->query->get('_widgetId', null);
        }

        if (!$widgetId) {
            return new WidgetOptionBag();
        }

        $widget       = $this->findWidget($widgetId);
        $widgetConfig = $this->configProvider->getWidgetConfig($widget->getName());
        $options      = $widget->getOptions();

        foreach ($widgetConfig['configuration'] as $name => $config) {
            $value          = isset($options[$name]) ? $options[$name] : null;
            $options[$name] = $this->valueProvider->getConvertedValue(
                $widgetConfig,
                $config['type'],
                $value,
                $config,
                $options
            );
        }

        return new WidgetOptionBag($options);
    }

    /**
     * @param Widget $widget
     * @return array
     */
    public function getFormValues(Widget $widget)
    {
        $options      = $widget->getOptions();
        $widgetConfig = $this->configProvider->getWidgetConfig($widget->getName());

        foreach ($widgetConfig['configuration'] as $name => $config) {
            $value          = isset($options[$name]) ? $options[$name] : null;
            $options[$name] = $this->valueProvider->getFormValue($config['type'], $config, $value);
        }

        $options = $this->loadDefaultValue($options, $widgetConfig);

        return $options;
    }

    /**
     * @param $options
     * @param $widgetConfig
     *
     * @return mixed
     */
    protected function loadDefaultValue($options, $widgetConfig)
    {
        if (!$options['title']['title'] || $options['title']['useDefault']) {
            $options['title']['title'] = $this->translator->trans($widgetConfig['label']);
            $options['title']['useDefault'] = true;
        }

        return $options;
    }

    /**
     * Filter widget configs based on acl enabled, applicable flag and selected items
     *
     * @param array   $items
     * @param boolean $applyVisible
     * @param array   $visibleItems
     *
     * @return array filtered items
     */
    protected function filterWidgets(array $items, $applyVisible = false, array $visibleItems = [])
    {
        $securityFacade = $this->securityFacade;
        $resolver       = $this->resolver;

        return array_filter(
            $items,
            function (&$item) use ($securityFacade, $resolver, $applyVisible, $visibleItems, &$items) {
                $visible = true;
                if ($applyVisible && !in_array(key($items), $visibleItems)) {
                    $visible = false;
                }
                next($items);
                $accessGranted = !isset($item['acl']) || $securityFacade->isGranted($item['acl']);
                $applicable    = true;
                $enabled       = $item['enabled'];
                if (isset($item['applicable'])) {
                    $resolved   = $resolver->resolve([$item['applicable']]);
                    $applicable = reset($resolved);
                }

                unset ($item['acl'], $item['applicable'], $item['enabled']);

                return $visible && $enabled && $accessGranted && $applicable;
            }
        );
    }

    /**
     * @param int $id
     *
     * @return Widget
     */
    protected function findWidget($id)
    {
        return $this->entityManager->getRepository('OroDashboardBundle:Widget')->find($id);
    }

    /**
     * @param array           $widgetConfig
     * @param WidgetOptionBag $widgetOptions
     * @return array|mixed
     */
    protected function getEnabledItems(array $widgetConfig, WidgetOptionBag $widgetOptions)
    {
        if (isset($widgetConfig['configuration'])) {
            foreach ($widgetConfig['configuration'] as $parameterName => $config) {
                if ($config['type'] === WidgetItemsChoiceType::NAME) {
                    return $widgetOptions->get($parameterName, []);
                }
            }
        }

        return [];
    }
}

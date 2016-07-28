<?php

namespace Oro\Bundle\DashboardBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WidgetFilterType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('entity', 'hidden', ['data' => $options['entity']]);
        $builder->add('definition', 'hidden', ['required' => false]);
        $factory = $builder->getFormFactory();
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($factory) {
                $form = $event->getForm();
                $data = $event->getData();
                $entity = $data ? $data['entity'] : null;
                $filterOptions = [
                    'mapped'             => false,
                    'column_choice_type' => null,
                    'entity'             => $entity,
                    'auto_initialize'    => false
                ];
                $form->add(
                    $factory->createNamed('filter', 'oro_query_designer_filter', null, $filterOptions)
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['widgetType']  = $options['widgetType'];
        $view->vars['collapsible'] = $options['collapsible'];
        $view->vars['collapsed']   = $options['collapsed'];
        parent::finishView($view, $form, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            ['widgetType' => null, 'entity' => null, 'collapsible' => false, 'collapsed' => true]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_dashboard_query_filter';
    }
}

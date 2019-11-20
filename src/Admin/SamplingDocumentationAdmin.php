<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Datagrid\{
    ListMapper,
    DatagridMapper
};
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\MediaBundle\Form\Type\MediaType;

class SamplingDocumentationAdmin extends AbstractAdmin
{
    protected $parentAssociationMapping = 'samplingActivity';

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('create_multiple');
    }

    public function configureActionButtons($action, $object = null)
    {
        if ($action == 'create_multiple' || $action == 'create') {
            return;
        } else {
            $list = parent::configureActionButtons($action, $object);
        }

        if (\in_array($action, ['tree', 'show', 'edit', 'delete', 'list', 'batch'], true)
            && $this->hasAccess('create')
            && $this->hasRoute('create')
        ) {
            $list['create_multiple']['template'] = 'SonataAdmin/CRUD/SamplingDocumentation/create_multiple_button.html.twig';
        }

        return $list;
    }

    public function getDashboardActions()
    {
        $actions = parent::getDashboardActions();

        if ($this->hasRoute('create') && $this->hasAccess('create')) {
            $actions['create_multiple'] = [
                'label' => 'Add many',
                'url' => $this->generateUrl('create_multiple'),
                'icon' => 'files-o',
            ];
        }

        return $actions;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper->addIdentifier('id')
            ->add('samplingActivity')
            ->add('samplingDocumentType')
            ->add('document')
            ->add('_action', 'actions', [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                ]
            ])
        ;
    }
 
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('samplingActivity')
            ->add('samplingDocumentType')
            ->add('document')
        ;
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        if (!$this->hasParentFieldDescription()) {
            $formMapper
                ->add('samplingActivity', ModelListType::class)
            ;
        }

	    $formMapper
            ->add('samplingDocumentType', ModelListType::class)
            ->add('document', MediaType::class, [
                'provider' => 'sonata.media.provider.file',
                'context' => 'default'
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('samplingActivity')
            ->add('samplingDocumentType')
            ->add('document')
            ->add('file', null, [
                'template' => 'SonataAdmin/CRUD/SamplingDocumentation/show_file.html.twig'
            ])
        ;
    }
}

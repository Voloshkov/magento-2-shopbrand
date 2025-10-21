<?php
namespace Magiccart\Shopbrand\Block\Adminhtml\Brand\Edit\Tab;

use Magiccart\Shopbrand\Model\Status;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Form extends \Magento\Backend\Block\Widget\Form\Generic
    implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /** @var \Magento\Framework\DataObjectFactory */
    protected $_objectFactory;

    /** @var \Magiccart\Shopbrand\Model\System\Config\Brand */
    protected $_brand;

    /** @var \Magiccart\Shopbrand\Model\Shopbrand */
    protected $_shopbrand;

    /** @var \Magiccart\Shopbrand\Helper\Data */
    protected $_helper;

    /** @var \Magento\Store\Model\System\Store */
    protected $_systemStore;

    /** @var \Magento\Cms\Model\Wysiwyg\Config */
    protected $_wysiwygConfig;

    /** @var Logger */
    private $brandLogger;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Store\Model\System\Store $systemStore,
        \Magento\Cms\Model\Wysiwyg\Config $wysiwygConfig,
        \Magiccart\Shopbrand\Model\Shopbrand $shopbrand,
        \Magiccart\Shopbrand\Model\System\Config\Brand $brand,
        \Magiccart\Shopbrand\Helper\Data $helper,
        array $data = []
    ) {
        $this->_objectFactory   = $objectFactory;
        $this->_shopbrand       = $shopbrand;
        $this->_brand           = $brand;
        $this->_helper          = $helper;
        $this->_systemStore     = $systemStore;
        $this->_wysiwygConfig   = $wysiwygConfig;
        
        $this->brandLogger = new Logger('brand');
        $this->brandLogger->pushHandler(
            new StreamHandler(BP . '/var/log/brand.log', Logger::DEBUG, true)
        );

        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _prepareLayout()
    {
        $this->getLayout()->getBlock('page.title')->setPageTitle($this->getPageTitle());
        return $this;
    }

    protected function _prepareForm()
    {
        try {
            $model = $this->_coreRegistry->registry('shopbrand');
            $this->brandLogger->debug('[SHOPBRAND-FORM] start', [
                'class'    => __CLASS__,
                'brand_id' => $model ? $model->getId() : null,
                'store_id' => $this->_storeManager->getStore()->getId()
            ]);
        } catch (\Throwable $e) {
            $this->brandLogger->debug('[SHOPBRAND-FORM] prelog error: ' . $e->getMessage());
            $model = $this->_coreRegistry->registry('shopbrand');
        }

        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('magic_');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => __('Brand Information [CHK]')
        ]);

        if ($model && $model->getId()) {
            $fieldset->addField('shopbrand_id', 'hidden', ['name' => 'shopbrand_id']);
        }

        $fieldset->addField('title', 'text', [
            'label'    => __('Title [CHK]'),
            'title'    => __('Title'),
            'name'     => 'title',
            'required' => true,
        ]);

        $fieldset->addField('urlkey', 'text', [
            'label'    => __('URL key'),
            'title'    => __('URL key'),
            'name'     => 'urlkey',
            'required' => true,
            'class'    => 'validate-xml-identifier',
        ]);

        $fieldset->addField('meta_key', 'text', [
            'label'    => __('Meta Keywords'),
            'title'    => __('Meta Keywords'),
            'name'     => 'meta_key',
            'required' => false,
        ]);

        $fieldset->addField('meta_description', 'text', [
            'label'    => __('Meta Description'),
            'title'    => __('Meta Description'),
            'name'     => 'meta_description',
            'required' => false,
        ]);

        try {
            $brandOptions = $this->_brand->toOptionArray();
        } catch (\Throwable $e) {
            $this->brandLogger->debug('[SHOPBRAND-FORM] brand options error: ' . $e->getMessage());
            $brandOptions = [];
        }
        if (array_filter($brandOptions)) {
            $fieldset->addField('option_id', 'select', [
                'label'   => __('Brand'),
                'title'   => __('Brand'),
                'name'    => 'option_id',
                'options' => $brandOptions,
            ]);
        }

        $fieldset->addField('image', 'image', [
            'label'    => __('Brand Logo'),
            'title'    => __('Brand Logo'),
            'name'     => 'image',
            'required' => true,
        ]);

        $fieldset->addField('description', 'editor', [
            'name'   => 'description',
            'label'  => __('Description'),
            'title'  => __('Description'),
            'config' => $this->_wysiwygConfig->getConfig([
                'add_variables'  => false,
                'add_widgets'    => true,
                'add_directives' => true
            ])
        ]);

        if (!$this->_storeManager->isSingleStoreMode()) {
            $field = $fieldset->addField('stores', 'multiselect', [
                'name'     => 'stores[]',
                'label'    => __('Store View'),
                'title'    => __('Store View'),
                'required' => true,
                'values'   => $this->_systemStore->getStoreValuesForForm(false, true)
            ]);
            $renderer = $this->getLayout()->createBlock(
                \Magento\Backend\Block\Store\Switcher\Form\Renderer\Fieldset\Element::class
            );
            if ($renderer) {
                $field->setRenderer($renderer);
            }
        } else {
            $fieldset->addField('stores', 'hidden', [
                'name'  => 'stores[]',
                'value' => $this->_storeManager->getStore(true)->getId()
            ]);
            if ($model) {
                $model->setStoreId($this->_storeManager->getStore(true)->getId());
            }
        }

        $fieldset->addField('status', 'select', [
            'label'   => __('Status'),
            'title'   => __('Status'),
            'name'    => 'status',
            'options' => Status::getAvailableStatuses(),
        ]);

        if ($model) {
            $form->addValues($model->getData());
        }
        $this->setForm($form);

        try {
            $names = [];
            foreach ($fieldset->getElements() as $element) {
                $names[] = $element->getName();
            }
            $this->brandLogger->debug('[SHOPBRAND-FORM] done', [
                'fieldset' => 'base_fieldset',
                'fields'   => $names
            ]);
        } catch (\Throwable $e) {
            $this->brandLogger->debug('[SHOPBRAND-FORM] postlog error: ' . $e->getMessage());
        }

        return parent::_prepareForm();
    }

    public function getShopbrand()
    {
        return $this->_coreRegistry->registry('shopbrand');
    }

    public function getPageTitle()
    {
        return $this->getShopbrand()->getId()
            ? __("Edit Brand '%1'", $this->escapeHtml($this->getShopbrand()->getTitle()))
            : __('New Brand');
    }

    public function getTabLabel()
    {
        return __('General Information');
    }

    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}

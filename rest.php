<?php

include_once _PS_MODULE_DIR_ . 'rest/restAppClass.php';
include_once _PS_MODULE_DIR_ . 'rest/restLogClass.php';

class Rest extends \Module
{
    protected $currentIndex;
    protected $restAppObject;

    public function __construct()
    {
        $this->name = 'rest';
        $this->displayName = 'Rest API';
        $this->tab = '';
        $this->version = '1.0.0';
        $this->author = 'Jonathan Sahm';

        $this->bootstrap = true;
        $this->display = 'view';

        $this->currentIndex = \AdminController::$currentIndex
            . '&configure=' . $this->name
            . '&token=' . \Tools::getAdminTokenLite('AdminModules');

        parent::__construct();
    }

    public function install()
    {
        $install = true;

        $install &= Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . \restAppClass::$definition['table'] . '` (
                `id_rest_app` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                `name` VARCHAR(45) NOT NULL,
                `public_key` VARCHAR(64) NOT NULL,
                `private_key` VARCHAR(64) NOT NULL,
                `token_key` VARCHAR(64) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_connection` DATETIME NOT NULL,
                PRIMARY KEY (`id_rest_app`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;
        ');

        $install &= Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . \restLogClass::$definition['table'] . '` (
                `id_rest_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_rest_app` INT(10) UNSIGNED NOT NULL,
                `method` VARCHAR(64) NOT NULL,
                `date` DATETIME NOT NULL,
                PRIMARY KEY (`id_rest_log`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;
        ');

        return $install
            && parent::install();
    }

    public function uninstall()
    {
        $uninstall = true;

        $uninstall &= Db::getInstance()->execute('
            DROP TABLE IF EXISTS `' . _DB_PREFIX_ . \restAppClass::$definition['table'] . '`
        ');

        $uninstall &= Db::getInstance()->execute('
            DROP TABLE IF EXISTS `' . _DB_PREFIX_ . \restLogClass::$definition['table'] . '`
        ');

        return $uninstall
            && parent::uninstall();
    }

    public function getContent()
    {
        $html = '<script type="text/javascript">
            function keyGen(size, id) {
                getE(id).value = "";
                var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                for (var i = 1; i <= size; ++i) getE(id).value += chars.charAt(Math.floor(Math.random() * chars.length));
            }
        </script>';
        $id_rest_app = (int) \Tools::getValue(\restAppClass::$definition['primary']);
        $this->restAppObject = new \restAppClass(0 === $id_rest_app ? null : $id_rest_app);

        if (Tools::isSubmit('save' . \restAppClass::$definition['table'])) {

            $saveAndStay = false;

            $this->restAppObject->copyFromPost();
            $this->restAppObject->id_shop = $this->context->shop->id;

            if (is_null($this->restAppObject->id)) {

                $saveAndStay = true;
            }

            if ($this->restAppObject->validateFields(false)) {

                $this->restAppObject->save();

                if (true === $saveAndStay) {

                    Tools::redirectAdmin($this->currentIndex . '&id_rest_app=' . $this->restAppObject->id . '&update' . \restAppClass::$definition['table']);
                }

            } else {

                $html .= '<div class="conf error">' . $this->l('An error occurred while attempting to save.') . '</div>';
            }
        }

        if (\Tools::isSubmit('update' . \restAppClass::$definition['table'])
            || \Tools::isSubmit('add' . \restAppClass::$definition['table'])) {

            $helper = $this->initForm();
            $helper->fields_value['name'] = $this->restAppObject->name;
            $helper->fields_value['private_key'] = $this->restAppObject->private_key;
            $helper->fields_value['public_key'] = $this->restAppObject->public_key;
            $helper->fields_value['active'] = $this->restAppObject->active;

            if (!is_null($this->restAppObject->id)) {

                $this->fields_form[0]['form']['input'][] = array(
                    'type' => 'hidden',
                    'name' => \restAppClass::$definition['primary']
                );

                $helper->fields_value[\restAppClass::$definition['primary']] = $id_rest_app;
                $helper->fields_value['last_connection'] = '0000-00-00 00:00:00' === $this->restAppObject->last_connection ? '-' : $this->restAppObject->last_connection;
                $helper->fields_value['token_key'] = empty($this->restAppObject->token_key) ? '-' : $this->restAppObject->token_key;
            }

            return $html . $helper->generateForm($this->fields_form);

        } elseif (\Tools::isSubmit('authorize' . \restAppClass::$definition['table'])) {

            $public_key = \Tools::getValue('public_key');
            $redirectTo = \Tools::getValue('redirect-to');

            if (!preg_match('#^https?://#', $redirectTo)) {

                $this->errors[] = Tools::displayError('Invalid redirect-to URL.');
                \Tools::redirectAdmin($this->currentIndex);
            }

            $id_rest_app = (int) Db::getInstance()->getValue('
                SELECT ra.`id_rest_app`
                FROM `' . _DB_PREFIX_ . \restAppClass::$definition['table'] . '` ra
                WHERE ra.`public_key` = "' . pSQL($public_key) . '"
                AND ra.`active` = 1
            ');

            if (0 === $id_rest_app) {

                $this->errors[] = Tools::displayError('Invalid public_key.');
                \Tools::redirectAdmin($this->currentIndex);
            }

            return $html . '
                <form action="' . $this->currentIndex . '" method="post" class="defaultForm form-horizontal rest">
                    <input type="hidden" name="redirect-to" value="' . $redirectTo . '" />
                    <input type="hidden" name="id_rest_app" value="' . $id_rest_app . '" />
                    <div id="fieldset_0" class="panel">
                        <div class="panel-heading">' . $this->l('Application authorization') . '</div>
                        <div class="panel-body">
                            ' . $this->l('This application can read') . '
                        </div>
                        <div class="panel-footer">
                            <button class="btn btn-success" name="token' . \restAppClass::$definition['table'] . '" value="1" type="submit">
                                <i class="process-icon-ok"></i> ' . $this->l('Authorize') . '
                            </button>
                        </div>
                    </div>
                </form>
	        ';

        } elseif (\Tools::isSubmit('token' . \restAppClass::$definition['table'])) {

            $this->restAppObject->token_key = hash('sha256', _COOKIE_KEY_ . microtime());
            $this->restAppObject->last_connection = date('Y-m-d H:i:s');
            $this->restAppObject->save();

            $this->restAppObject->setLog('getToken');

            $url = parse_url(\Tools::getValue('redirect-to'));

            \Tools::redirect($url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query'] ? $url['query'] . '&' : '?') . 'token=' . $this->restAppObject->token_key);

        } elseif (\Tools::isSubmit('active' . \restAppClass::$definition['table'])) {

            $this->restAppObject->active = (int) !(bool)$this->restAppObject->active;
            $this->restAppObject->save();

            \Tools::redirectAdmin($this->currentIndex);

        } elseif (\Tools::isSubmit('delete' . \restAppClass::$definition['table'])) {

            $this->restAppObject->delete();

            \Tools::redirectAdmin($this->currentIndex);

        } else {

            $helper = $this->initList();

            return $html . $helper->generateList($this->getListContent(), $this->fields_list);
        }
    }

    protected function getListContent()
    {
        return  Db::getInstance()->executeS('
			SELECT ra.`id_rest_app`, ra.`id_shop`, ra.`name`, ra.`active`
			FROM `' . _DB_PREFIX_ . \restAppClass::$definition['table'] . '` ra
	    ');
    }

    protected function initList()
    {
        $this->fields_list = array(
            \restAppClass::$definition['primary'] => array(
                'title' => $this->l('ID'),
                'width' => 120,
                'type' => 'text',
                'search' => false,
                'orderby' => false
            ),
            'name' => array(
                'title' => $this->l('Name'),
                'width' => 140,
                'type' => 'text',
                'search' => false,
                'orderby' => false
            ),
            'last_connection' => array(
                'title' => $this->l('Last connection'),
                'width' => 140,
                'type' => 'text',
                'search' => false,
                'orderby' => false
            ),
            'active' => array(
                'title' => $this->l('Enabled'),
                'align' => 'center',
                'active' => 'active',
                'type' => 'bool',
                'orderby' => false,
                'class' => 'fixed-width-xs'
            )
        );

        if (\Shop::isFeatureActive()) {

            $this->fields_list['id_shop'] = array(
                'title' => $this->l('ID Shop'),
                'align' => 'center',
                'width' => 25,
                'type' => 'int'
            );
        }

        $helper = new \HelperListCore();
        $helper->shopLinkType = '';
        $helper->identifier = \restAppClass::$definition['primary'];
        $helper->listTotal = count($this->getListContent());
        $helper->actions = array('edit', 'delete');
        $helper->title = $this->l('Applications');
        $helper->table = \restAppClass::$definition['table'];
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;
        $helper->show_toolbar = true;
        $helper->toolbar_btn['new'] =  array(
            'href' => $this->currentIndex . '&add' . \restAppClass::$definition['table'],
            'desc' => $this->l('Add new')
        );

        return $helper;
    }

    protected function initForm()
    {
        $this->fields_form[0]['form'] = array(
            'legend' => array(
                'title' => is_null($this->restAppObject->id) ? $this->l('Create application') : $this->l('Edit application')
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'hint' => $this->l('Application name'),
                    'required' => true
                ),
                array(
                    'type' => 'textbutton',
                    'label' => $this->l('Public key'),
                    'name' => 'public_key',
                    'id' => 'public_key',
                    'required' => true,
                    'hint' => $this->l('REST public key.'),
                    'button' => array(
                        'label' => $this->l('Generate!'),
                        'attributes' => array(
                            'onclick' => 'keyGen(64, \'public_key\')'
                        )
                    )
                ),
                array(
                    'type' => 'textbutton',
                    'label' => $this->l('Private key'),
                    'name' => 'private_key',
                    'id' => 'private_key',
                    'required' => true,
                    'hint' => $this->l('REST private key.'),
                    'button' => array(
                        'label' => $this->l('Generate!'),
                        'attributes' => array(
                            'onclick' => 'keyGen(64, \'private_key\')'
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Status'),
                    'name' => 'active',
                    'required' => true,
                    'hint' => $this->l('Application status.'),
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Save')
            )
        );

        if (!is_null($this->restAppObject->id)) {

            $this->fields_form[0]['form']['input'][] = array(
                'type' => 'html',
                'label' => $this->l('Link'),
                'name' => __PS_BASE_URI__
                    . $this->currentIndex
                    . '&authorize' . \restAppClass::$definition['table']
                    . '&public_key=' . $this->restAppObject->public_key
                    . '&redirect-to='
            );

            $this->fields_form[0]['form']['input'][] = array(
                'type' => 'free',
                'label' => $this->l('Last connection'),
                'name' => 'last_connection'
            );

            $this->fields_form[0]['form']['input'][] = array(
                'type' => 'free',
                'label' => $this->l('Token key'),
                'name' => 'token_key'
            );
        }

        $helper = new \HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'rest';
        $helper->identifier = \restAppClass::$definition['primary'];
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;
        $helper->title = $this->displayName;
        $helper->submit_action = 'save' . \restAppClass::$definition['table'];
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->toolbar_btn =  array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => $this->currentIndex . '&save' . \restAppClass::$definition['table'],
            )
        );

        return $helper;
    }
}

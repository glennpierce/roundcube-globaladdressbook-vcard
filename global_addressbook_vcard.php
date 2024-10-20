<?php

class vcard_addressbook_backend extends rcube_addressbook
{
    public $primary_key = 'ID';
    public $readonly = true;
    public $groups = false;  // Assuming no groups for now
    private $name;
    private $contacts = [];

    public function __construct($name, $vcard_file)
    {
        $this->ready = true;
        $this->name = $name;
        $this->load_vcard_file($vcard_file);
    }

    /**
     * Load the contacts from a .vcard file
     */
    private function load_vcard_file($vcard_file)
    {
        if (file_exists($vcard_file)) {
            $vcard_data = file_get_contents($vcard_file);
            $this->contacts = $this->parse_vcards($vcard_data);
        }
    }

    /**
     * Parse VCard data into a list of contacts
     */
    private function parse_vcards($vcard_data)
    {
        $contacts = [];
        $vcard_parser = new Sabre\VObject\Reader();
        $vcard_objects = $vcard_parser::read($vcard_data);

        foreach ($vcard_objects->VCARD as $vcard) {
            $contacts[] = [
                'ID' => (string) $vcard->UID,
                'name' => (string) $vcard->FN,
                'firstname' => (string) $vcard->GIVENNAME,
                'surname' => (string) $vcard->FAMILYNAME,
                'email' => (string) $vcard->EMAIL,
            ];
        }
        return $contacts;
    }

    #[Override]
    public function list_records($cols = null, $subset = 0, $nocount = false)
    {
        return $this->contacts;
    }

    #[Override]
    public function get_name()
    {
        return $this->name;
    }
}

class vcard_addressbook extends rcube_plugin
{
    private $abook_id = 'vcard';

    #[Override]
    public function init()
    {
        $this->add_hook('addressbooks_list', [$this, 'address_sources']);
        $this->add_hook('addressbook_get', [$this, 'get_address_book']);
    }

    public function address_sources($p)
    {
        $config = rcmail::get_instance()->config;
        $vcard_directory = $config->get('vcard_directory');

        if (is_dir($vcard_directory)) {
            $vcard_files = glob($vcard_directory . '/*.vcard');
            foreach ($vcard_files as $vcard_file) {
                $abook_name = basename($vcard_file, '.vcard');
                $abook = new vcard_addressbook_backend($abook_name, $vcard_file);

                $p['sources'][$this->abook_id . '_' . $abook_name] = [
                    'id' => $this->abook_id . '_' . $abook_name,
                    'name' => $abook_name,
                    'readonly' => $abook->readonly,
                    'groups' => $abook->groups,
                ];
            }
        }

        return $p;
    }

    public function get_address_book($p)
    {
        $config = rcmail::get_instance()->config;
        $vcard_directory = $config->get('vcard_directory');

        if (strpos($p['id'], $this->abook_id . '_') === 0) {
            $abook_name = substr($p['id'], strlen($this->abook_id . '_'));
            $vcard_file = $vcard_directory . '/' . $abook_name . '.vcard';

            if (file_exists($vcard_file)) {
                $p['instance'] = new vcard_addressbook_backend($abook_name, $vcard_file);
            }
        }

        return $p;
    }
}


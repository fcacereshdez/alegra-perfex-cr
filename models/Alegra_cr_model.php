<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Alegra_cr_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('encryption');
    }

    public function get_settings()
    {
        $this->db->where('id', 1); // Usar ID fijo para settings globales
        $result = $this->db->get(db_prefix() . 'alegra_cr_integrations')->row_array();

        if ($result) {
            $result['alegra_email'] = $this->encryption->decrypt($result['alegra_email']);
        }

        return $result;
    }

    public function save_settings($data)
    {
        if (empty($data['alegra_email']) || empty($data['alegra_token'])) {
            return false;
        }

        $settings_data = [
            'alegra_email' => $this->encryption->encrypt($data['alegra_email']),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['alegra_token'])) {
            $settings_data['alegra_token'] = $this->encryption->encrypt($data['alegra_token']);
        }

        $this->db->where('id', 1);
        $existing_settings = $this->db->get(db_prefix() . 'alegra_cr_integrations')->row();

        if ($existing_settings) {
            $this->db->where('id', 1);
            return $this->db->update(db_prefix() . 'alegra_cr_integrations', $settings_data);
        } else {
            $settings_data['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert(db_prefix() . 'alegra_cr_integrations', $settings_data);
        }
    }

    // En Alegra_cr_model.php - Agregar estas funciones

public function get_invoice_map($perfex_invoice_id)
{
    $this->db->where('perfex_invoice_id', $perfex_invoice_id);
    $query = $this->db->get(db_prefix() . 'alegra_cr_invoices_map');
    return $query->row_array();
}

public function save_invoice_map($data)
{
    // Verificar si ya existe un mapeo
    $existing = $this->get_invoice_map($data['perfex_invoice_id']);
    
    if ($existing) {
        $this->db->where('perfex_invoice_id', $data['perfex_invoice_id']);
        return $this->db->update(db_prefix() . 'alegra_cr_invoices_map', $data);
    } else {
        return $this->db->insert(db_prefix() . 'alegra_cr_invoices_map', $data);
    }
}

public function update_invoice_map_status($perfex_invoice_id, $status, $response_data = null)
{
    $data = [
        'status' => $status,
        'sync_date' => date('Y-m-d H:i:s')
    ];
    
    if ($response_data) {
        $data['response_data'] = is_array($response_data) ? json_encode($response_data) : $response_data;
    }
    
    $this->db->where('perfex_invoice_id', $perfex_invoice_id);
    return $this->db->update(db_prefix() . 'alegra_cr_invoices_map', $data);
}

public function get_alegra_invoice_id($perfex_invoice_id)
{
    $map = $this->get_invoice_map($perfex_invoice_id);
    return $map ? $map['alegra_invoice_id'] : null;
}

public function get_invoices_map()
{
    $map = [];
    $query = $this->db->get(db_prefix() . 'alegra_cr_invoices_map');
    foreach ($query->result() as $row) {
        $map[$row->perfex_invoice_id] = [
            'alegra_invoice_id' => $row->alegra_invoice_id,
            'alegra_invoice_number' => $row->alegra_invoice_number,
            'status' => $row->status,
            'sync_date' => $row->sync_date
        ];
    }
    return $map;
}

    public function get_products_map()
    {
        $map = [];
        $query = $this->db->get(db_prefix() . 'alegra_cr_products_map');
        foreach ($query->result() as $row) {
            $map[$row->perfex_item_id] = $row->alegra_item_id;
        }
        return $map;
    }

    public function get_product_map($perfex_item_id)
    {
        $this->db->where('perfex_item_id', $perfex_item_id);
        $query = $this->db->get(db_prefix() . 'alegra_cr_products_map');
        return $query->row_array();
    }

    public function add_product_map($itemid, $alegra_item_id)
    {
        // Verificar si ya existe
        $existing = $this->get_product_map($itemid);
        if ($existing) {
            $this->db->where('perfex_item_id', $itemid);
            return $this->db->update(db_prefix() . 'alegra_cr_products_map', [
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $data = [
                'perfex_item_id' => $itemid,
                'alegra_item_id' => $alegra_item_id,
                'last_sync_date' => date('Y-m-d H:i:s'),
            ];
            return $this->db->insert(db_prefix() . 'alegra_cr_products_map', $data);
        }
    }

    public function get_api_credentials()
    {
        $this->db->where('id', 1);
        $result = $this->db->get(db_prefix() . 'alegra_cr_integrations')->row_array();

        if ($result) {
            $result['alegra_email'] = $this->encryption->decrypt($result['alegra_email']);
            $result['alegra_token'] = $this->encryption->decrypt($result['alegra_token']);
        }

        return $result;
    }
}
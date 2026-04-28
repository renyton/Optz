<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Activator
{
    public static function activate()
    {
        Optimize_Kommo_DB::create_tables();

        add_option('optimize_kommo_subdomain', '');
        add_option('optimize_kommo_client_id', '');
        add_option('optimize_kommo_client_secret', '');
        add_option('optimize_kommo_oauth_access_token', '');
        add_option('optimize_kommo_oauth_refresh_token', '');
        add_option('optimize_kommo_oauth_expires_at', 0);
        add_option('optimize_kommo_oauth_connected_at', '');
        add_option('optimize_kommo_oauth_state', '');
        add_option('optimize_kommo_interval', 10);
        add_option('optimize_kommo_authorized_users', '');
        add_option('optimize_kommo_last_sync', '');
    }
}

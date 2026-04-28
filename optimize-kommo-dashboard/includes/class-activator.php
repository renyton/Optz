<?php

if (! defined('ABSPATH')) {
    exit;
}

class Optimize_Kommo_Activator
{
    public static function ensure_roles_and_caps()
    {
        add_role(
            'optimize_dashboard_viewer',
            __('Visualizador Dashboard Optimize', 'optimize-kommo-dashboard'),
            [
                'read' => true,
                'access_optimize_dashboard' => true,
            ]
        );

        $admin = get_role('administrator');
        if ($admin && ! $admin->has_cap('access_optimize_dashboard')) {
            $admin->add_cap('access_optimize_dashboard');
        }
    }

    public static function activate()
    {
        Optimize_Kommo_DB::create_tables();
        self::ensure_roles_and_caps();

        add_option('optimize_kommo_subdomain', '');
        add_option('optimize_kommo_token', '');
        add_option('optimize_kommo_interval', 10);
        add_option('optimize_kommo_authorized_users', '');
        add_option('optimize_kommo_last_sync', '');

    }
}

<?php

return [
    'welcome' => 'Welcome',
    'tenant_suspended' => 'Service for this villa is temporarily unavailable.',

    'nav' => [
        'tenants' => 'Tenants',
        'plans' => 'Plans',
        'subscriptions' => 'Subscriptions',
        'users' => 'Users',
    ],

    'models' => [
        'tenant' => 'Tenant',
        'plan' => 'Plan',
        'subscription' => 'Subscription',
        'user' => 'User',
    ],

    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'subdomain' => 'Subdomain',
        'custom_domain' => 'Custom domain',
        'status' => 'Status',
        'default_locale' => 'Default language',
        'timezone' => 'Timezone',
        'currency' => 'Currency',
        'price' => 'Price',
        'max_rooms' => 'Maximum rooms',
        'interval' => 'Billing period',
        'is_active' => 'Active',
        'tenant' => 'Tenant',
        'tenants' => 'Tenants',
        'plan' => 'Plan',
        'starts_at' => 'Starts',
        'ends_at' => 'Ends',
        'grace_until' => 'Grace period until',
        'email' => 'Email',
        'password' => 'Password',
        'is_super_admin' => 'Super admin',
    ],

    'help' => [
        'subdomain' => 'Site address: {subdomain}.base-domain. Leave empty to use the slug.',
    ],

    'tenant_status' => [
        'trial' => 'Trial',
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'subscription_status' => [
        'trial' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Past due',
        'canceled' => 'Canceled',
    ],

    'intervals' => [
        'month' => 'Monthly',
        'year' => 'Yearly',
    ],

    'widgets' => [
        'plan' => 'Plan',
        'room_limit' => 'Room limit',
        'valid_until' => 'Valid until',
    ],
];

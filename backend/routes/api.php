<?php

return [
    ['GET', '/api/health', 'health'],

    ['POST', '/api/register', 'auth.register'],
    ['POST', '/api/auth/register', 'auth.register'],
    ['POST', '/api/auth/login', 'auth.login'],
    ['GET', '/api/auth/csrf', 'auth.csrf'],
    ['GET', '/api/activate', 'auth.activate'],

    ['GET', '/categories', 'categories.list'],
    ['GET', '/api/categories', 'categories.list'],
    ['GET', '/products', 'products.list'],
    ['GET', '/api/products', 'products.list'],
    ['GET', '/products/{id}', 'products.show'],
    ['GET', '/api/products/{id}', 'products.show'],

    ['GET', '/api/cart', 'cart.get'],
    ['POST', '/api/cart/add', 'cart.add'],
    ['PATCH', '/api/cart/update', 'cart.update'],
    ['DELETE', '/api/cart/remove', 'cart.remove'],

    ['GET', '/api/orders', 'orders.list'],
    ['POST', '/api/orders', 'orders.create'],
    ['POST', '/api/orders/{id}/cancel', 'orders.cancel'],

    ['POST', '/api/payment/create', 'payment.create'],
    ['POST', '/api/payment/confirm', 'payment.confirm'],
    ['POST', '/api/payment/webhook', 'payment.webhook'],
    ['POST', '/api/checkout/create-session', 'checkout.createSession'],
    ['POST', '/api/checkout/confirm-session', 'checkout.confirmSession'],

    ['GET', '/api/user/profile', 'user.profile'],
    ['GET', '/api/user/addresses', 'user.addresses'],
    ['POST', '/api/user/addresses', 'user.addAddress'],
    ['GET', '/api/user/orders', 'user.orders'],

    ['GET', '/api/admin/stats', 'admin.stats'],
    ['GET', '/api/admin/users', 'admin.users'],
    ['PATCH', '/api/admin/users/{id}', 'admin.users.patch'],
    ['DELETE', '/api/admin/users/{id}', 'admin.users.delete'],
    ['GET', '/api/admin/orders', 'admin.orders'],
    ['PATCH', '/api/admin/orders/{id}', 'admin.orders.updateStatus'],
    ['GET', '/api/admin/logs', 'admin.logs'],
    ['GET', '/api/admin/coupons', 'admin.coupons.list'],
    ['POST', '/api/admin/coupons', 'admin.coupons.create'],
    ['PATCH', '/api/admin/coupons/{id}', 'admin.coupons.patch'],
    ['DELETE', '/api/admin/coupons/{id}', 'admin.coupons.delete'],
    ['POST', '/api/admin/upload', 'admin.upload'],
    ['POST', '/api/admin/products', 'products.create'],
    ['POST', '/api/admin/products/{id}/image', 'admin.products.addImage'],
    ['PUT', '/api/admin/products/{id}', 'products.update'],
    ['DELETE', '/api/admin/products/{id}', 'products.delete'],
];

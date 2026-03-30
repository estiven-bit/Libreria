<?php

return [
    ['GET', '/api/health', 'health'],

    ['POST', '/api/register', 'auth.register'],
    ['POST', '/api/auth/register', 'auth.register'],
    ['POST', '/api/auth/login', 'auth.login'],
    ['GET', '/api/activate', 'auth.activate'],

    ['GET', '/categories', 'categories.list'],
    ['GET', '/products', 'products.list'],
    ['GET', '/products/{id}', 'products.show'],

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

    ['GET', '/api/user/profile', 'user.profile'],
    ['GET', '/api/user/addresses', 'user.addresses'],
    ['POST', '/api/user/addresses', 'user.addAddress'],

    ['GET', '/api/admin/stats', 'admin.stats'],
    ['GET', '/api/admin/users', 'admin.users'],
    ['GET', '/api/admin/coupons', 'coupons.list'],
    ['POST', '/api/admin/coupons', 'coupons.create'],
    ['POST', '/api/admin/upload', 'admin.upload'],
    ['POST', '/api/admin/products', 'products.create'],
    ['PUT', '/api/admin/products/{id}', 'products.update'],
    ['DELETE', '/api/admin/products/{id}', 'products.delete'],
];

## Ejemplos de endpoints REST

GET /api/health

POST /api/auth/register
Body: { "name": "Ana", "email": "ana@email.com", "password": "123456" }

POST /api/auth/login
Body: { "email": "ana@email.com", "password": "123456" }

GET /api/categories

GET /api/products?search=aventuras&category_id=2

GET /api/products/1

GET /api/cart
POST /api/cart/add
PATCH /api/cart/update
DELETE /api/cart/remove

GET /api/orders
POST /api/orders
POST /api/orders/10/cancel

POST /api/payment/create
POST /api/payment/confirm
POST /api/payment/webhook

GET /api/admin/stats
GET /api/admin/users
GET /api/admin/coupons
POST /api/admin/coupons
POST /api/admin/products
PUT /api/admin/products/1
DELETE /api/admin/products/1
POST /api/admin/upload

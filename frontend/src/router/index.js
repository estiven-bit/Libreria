import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import CatalogView from '../views/CatalogView.vue'
import ProductView from '../views/ProductView.vue'
import CartView from '../views/CartView.vue'
import CheckoutView from '../views/CheckoutView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ProfileView from '../views/ProfileView.vue'
import OrdersView from '../views/OrdersView.vue'
import ContactView from '../views/ContactView.vue'
import AdminDashboardView from '../views/admin/AdminDashboardView.vue'
import AdminProductsView from '../views/admin/AdminProductsView.vue'
import AdminOrdersView from '../views/admin/AdminOrdersView.vue'
import AdminUsersView from '../views/admin/AdminUsersView.vue'
import AdminCouponsView from '../views/admin/AdminCouponsView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import ActivateView from '../views/Activate.vue' // Asegúrate de que el nombre del archivo sea exacto

const routes = [
  { path: '/', name: 'home', component: HomeView, meta: { title: 'Inicio', description: 'Libros infantiles para todas las edades.' } },
  { path: '/catalogo', name: 'catalog', component: CatalogView, meta: { title: 'Catalogo', description: 'Explora libros infantiles por categoria.' } },
  { path: '/product/:slug', name: 'product', component: ProductView, meta: { title: 'Detalle de producto', description: 'Descubre libros con ilustraciones y aventuras.' } },
  { path: '/carrito', name: 'cart', component: CartView, meta: { title: 'Carrito', description: 'Revisa tu carrito de compras.' } },
  { path: '/checkout', name: 'checkout', component: CheckoutView, meta: { title: 'Checkout', description: 'Finaliza tu compra de forma segura.' } },
  { path: '/login', name: 'login', component: LoginView, meta: { title: 'Login', description: 'Accede a tu cuenta.' } },
  { path: '/registro', name: 'register', component: RegisterView, meta: { title: 'Registro', description: 'Crea tu cuenta en Libreria Gabi.' } },
  { path: '/perfil', name: 'profile', component: ProfileView, meta: { title: 'Perfil', description: 'Gestiona tu perfil y direcciones.' } },
  { path: '/pedidos', name: 'orders', component: OrdersView, meta: { title: 'Pedidos', description: 'Consulta tu historial de pedidos.' } },
  { path: '/contacto', name: 'contact', component: ContactView, meta: { title: 'Contacto', description: 'Escribenos para ayudarte.' } },
  { path: '/admin', name: 'admin', component: AdminDashboardView, meta: { title: 'Admin', description: 'Panel administrador.' } },
  { path: '/admin/productos', name: 'admin-products', component: AdminProductsView },
  { path: '/admin/pedidos', name: 'admin-orders', component: AdminOrdersView },
  { path: '/admin/usuarios', name: 'admin-users', component: AdminUsersView },
  { path: '/admin/cupones', name: 'admin-coupons', component: AdminCouponsView },
  { path: '/activate', name: 'activate', component: ActivateView, meta: { title: 'Activar Cuenta', description: 'Verificación de tu cuenta en Librería Gabi.' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'No encontrado', description: 'La pagina solicitada no existe.' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.afterEach((to) => {
  const baseTitle = 'Pagina Web Gabi'
  document.title = to.meta?.title ? `${to.meta.title} | ${baseTitle}` : baseTitle
  const description = to.meta?.description || 'Libreria Gabi - libros infantiles.'
  let meta = document.querySelector('meta[name=\"description\"]')
  if (meta) {
    meta.setAttribute('content', description)
  }
})

export default router

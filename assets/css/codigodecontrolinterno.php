<?php  
session_start();
include('config.php'); // Conexión a la base de datos

// Filtro por categoría si existe
$categoriaFilter = "";
if (isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $categoria = $conexion->real_escape_string($_GET['categoria']);
    $categoriaFilter = " AND c.nombre = '$categoria' ";
}

// Consulta para obtener los productos
$query = "
  SELECT
    p.id AS product_id,
    p.nombre COLLATE utf8_general_ci AS nombre,
    p.descripcion COLLATE utf8_general_ci AS descripcion,
    p.imagen COLLATE utf8_general_ci AS imagen,
    p.precio_venta AS precio,
    p.stock AS stock,
    p.tamano COLLATE utf8_general_ci AS tamano,
    c.nombre COLLATE utf8_general_ci AS categoria_nombre,
    (SELECT COUNT(*) FROM producto_variantes pv WHERE pv.producto_id = p.id) AS variant_count,
    (SELECT MIN(id) FROM producto_variantes pv WHERE pv.producto_id = p.id) AS first_variant_id
  FROM productos p
  JOIN categorias c ON p.categoria_id = c.id
  WHERE p.id NOT IN (
      SELECT producto_id FROM ofertas 
      WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin
  )
  $categoriaFilter
  ORDER BY product_id DESC
";
$resultado = $conexion->query($query) or die("Error en la consulta: " . $conexion->error);

// Consulta para cargar las categorías
$queryCategorias = "SELECT nombre FROM categorias ORDER BY nombre ASC";
$resultCategorias = $conexion->query($queryCategorias) or die("Error al cargar categorías: " . $conexion->error);

// Variable que indica si el usuario está logueado
$loggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Productos - Yarlean Nails</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Íconos y Fuentes -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
  
  <script>
    window.openLoginModal = function() {
      document.getElementById('loginModal').style.display = 'block';
    };
  </script>
  
  <!-- Script para restaurar la posición de scroll al cargar la página -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const savedScroll = localStorage.getItem("savedScrollPos");
      if (savedScroll !== null) {
        // Se usa un pequeño retardo para asegurarse de que el layout ya esté renderizado
        setTimeout(function(){
          window.scrollTo({ top: parseInt(savedScroll, 10) });
          localStorage.removeItem("savedScrollPos");
        }, 100);
      }
    });
  </script>
  
  <!-- Script para guardar la posición de scroll al hacer clic en una tarjeta de producto -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      document.body.addEventListener("click", function(event) {
        const productCard = event.target.closest('.product-card');
        if (productCard) {
          // Guardar la posición actual del scroll en localStorage
          localStorage.setItem("savedScrollPos", window.pageYOffset);
        }
      }, true); // true para capturar en la fase de captura
    });
  </script>
  
  <style>
   /* RESET Y ESTILOS GLOBALES */
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}
* {
  box-sizing: border-box;
  font-family: 'Lato', sans-serif;
}
:root {
  --primary-black: #000;
  --secondary-black: #333;
  --white: #fff;
  /* --bg-gray: #f7f7f7;  (ya no lo usamos) */
  --heading-font: 'Playfair Display', serif;
}
body {
  background-color: #FFD7EA; /* Rosa pastel en todo el fondo */
  color: var(--primary-black);
  overflow-x: hidden;
  padding-top: 80px; /* Para no tapar el navbar fijo */
}
a {
  text-decoration: none;
  color: inherit;
}

/* NAVBAR */
.navbar {
  background: var(--white);
  padding: 1rem 2rem;
  position: fixed;
  width: 100%;
  top: 0;
  z-index: 1100;
  border-bottom: 1px solid var(--secondary-black);
}
.nav-content {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
  width: 100%;
}
.hamburger {
  font-size: 1.8rem;
  cursor: pointer;
  color: var(--primary-black);
  transition: all 0.3s ease;
}
.hamburger:hover {
  opacity: 0.8;
}
.logo {
  color: var(--primary-black);
  font-family: var(--heading-font);
  font-size: 1.8rem;
  font-weight: 500;
}
.login-btn {
  background: transparent;
  border: 1px solid var(--primary-black);
  color: var(--primary-black);
  font-size: 1rem;
  padding: 0.5rem 1rem;
  border-radius: 5px;
  cursor: pointer;
  transition: background 0.3s ease, color 0.3s ease;
}
.login-btn:hover {
  background: var(--primary-black);
  color: var(--white);
}
.user-welcome {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 150px;
  display: inline-block;
  vertical-align: middle;
}
.cart-icon {
  margin-left: auto;
}

/* SIDEBAR (NAVEGACIÓN) */
.sidebar {
  position: fixed;
  left: -300px;
  top: 0;
  height: 100vh;
  width: 280px;
  background: var(--white);
  padding: 2rem;
  border-right: 1px solid var(--secondary-black);
  border-radius: 0 15px 15px 0;
  transition: all 0.4s;
  z-index: 2000;
}
.sidebar.active {
  left: 0;
}
.sidebar-links {
  margin-top: 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.sidebar-links a {
  font-size: 1.1rem;
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  transition: all 0.3s ease;
}
.sidebar-links a:hover {
  background: rgba(0,0,0,0.05);
  transform: translateX(10px);
}
.close-btn {
  color: var(--primary-black);
  font-size: 2rem;
  cursor: pointer;
  position: absolute;
  right: 1.5rem;
  top: 1rem;
  transition: all 0.3s ease;
}
.close-btn:hover {
  transform: rotate(90deg);
}

/* SIDEBAR DEL CARRITO */
.cart-sidebar {
  position: fixed;
  top: 0;
  right: -350px;
  width: 300px;
  height: 100vh;
  background: var(--white);
  box-shadow: -2px 0 5px rgba(0,0,0,0.1);
  padding: 2rem;
  transition: all 0.4s ease;
  z-index: 2100;
  overflow-y: auto;
}
.cart-sidebar.active {
  right: 0;
}
.cart-sidebar .close-cart {
  font-size: 2rem;
  cursor: pointer;
  color: var(--primary-black);
  position: absolute;
  right: 1rem;
  top: 1rem;
}
.cart-sidebar h3 {
  font-family: var(--heading-font);
  margin-bottom: 1rem;
  text-align: center;
}
#cart-items {
  list-style: none;
  margin-bottom: 1rem;
  padding: 0;
}
#cart-items li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid #ddd;
}
#cart-total {
  font-weight: bold;
  text-align: center;
  margin-top: 1rem;
}
#checkout-btn {
  display: block;
  width: 100%;
  margin-top: 1rem;
  background: var(--primary-black);
  color: var(--white);
  border: none;
  padding: 0.75rem;
  border-radius: 5px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.3s ease;
}
#checkout-btn:hover {
  background: var(--secondary-black);
}

/* CONTENEDOR PRINCIPAL Y FILTRO DE CATEGORÍAS */
.main-content {
  display: flex;
  gap: 2rem;
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
  min-height: calc(100vh - 80px); /* Para que crezca hasta llenar la pantalla, restando la altura del navbar */
}
.filter {
  width: 250px;
  background: var(--white);
  padding: 1rem;
  border: 1px solid var(--secondary-black);
  border-radius: 10px;
  height: fit-content;
  margin-top: 20px !important;
  flex-shrink: 0;
}
.filter h3 {
  font-family: var(--heading-font);
  margin-bottom: 1rem;
}
.filter ul {
  list-style: none;
}
.filter ul li {
  margin-bottom: 0.75rem;
}
.filter ul li a {
  color: var(--primary-black);
  transition: color 0.3s ease;
}
.filter ul li a:hover {
  color: var(--secondary-black);
}

/* SECCIÓN DE PRODUCTOS */
.products {
  flex: 1;
  min-width: 0;
}
.search-container {
  margin-bottom: 1.5rem;
  text-align: right;
}
#search-bar {
  width: 100%;
  max-width: 300px;
  padding: 0.5rem 1rem;
  border: 1px solid var(--secondary-black);
  border-radius: 5px;
  outline: none;
}

/* ======================
   AJUSTES PARA ALINEAR BOTONES
   ====================== */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  align-items: stretch;
}
.product-card {
  background: var(--white);
  border: 1px solid #ddd;
  border-radius: 10px;
  overflow: hidden;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  position: relative;
  cursor: pointer;
  display: flex;
  flex-direction: column;
}
/* En escritorio la imagen mantiene altura fija */
.product-card img {
  width: 100%;
  height: 220px;
  object-fit: cover;
  display: block;
}
/* Info del producto: fuerza que ocupe el espacio restante y que el botón quede abajo */
.product-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1rem;
  text-align: center;
}
.product-info h4 {
  font-family: var(--heading-font);
  margin-bottom: 0.5rem;
}
.product-info .price {
  font-weight: bold;
  margin-bottom: 0.5rem;
}
.add-to-cart {
  background: var(--primary-black);
  color: var(--white);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  transition: background 0.3s ease;
  font-size: 0.9rem;
  margin-top: 1rem;
}
.add-to-cart:hover {
  background: var(--secondary-black);
}

/* PAGINACIÓN */
.pagination {
  margin-top: 2rem;
  text-align: center;
}
.pagination a {
  margin: 0 0.5rem;
  color: var(--primary-black);
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--secondary-black);
  border-radius: 5px;
  transition: background 0.3s ease;
}
.pagination a:hover {
  background: var(--secondary-black);
  color: var(--white);
}

/* MEDIA QUERIES */
@media (max-width: 600px) {
  .main-content {
    flex-direction: column;
    padding: 1rem;
  }
  .filter {
    width: 100%;
    margin-top: 20px !important;
    margin-bottom: 1rem;
  }
  .products-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
  }
  .product-card img {
    /* Opcional: si deseas que sean cuadradas en móviles */
    aspect-ratio: 1/1;
    height: auto;
  }
  .product-info {
    padding: 0.5rem;
  }
  .product-info h4 {
    font-size: 0.9rem;
  }
  .add-to-cart {
    font-size: 0.8rem;
    padding: 0.3rem 0.5rem;
  }
  .cart-sidebar {
    width: 100%;
    right: -100%;
    top: 80px;
  }
  .cart-sidebar.active {
    right: 0;
  }
}
@media (min-width: 601px) and (max-width: 900px) {
  .main-content {
    flex-direction: column;
  }
  .filter {
    width: 100%;
    margin-top: 20px !important;
    margin-bottom: 1rem;
  }
  .products-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 1.2rem;
  }
  .product-card img {
    /* Opcional: si deseas que sean cuadradas en tabletas */
    aspect-ratio: 1/1;
    height: auto;
  }
}
@media (min-width: 901px) {
  .main-content {
    flex-direction: row;
  }
  .products-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  }
  .product-card img {
    height: 220px;
  }
}

/* ======================
   MODAL DE LOGIN CON ANIMACIÓN
   ====================== */
.modal {
  display: none;
  position: fixed;
  z-index: 3000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.5);
}
.modal-content {
  background-color: #fefefe;
  margin: 10% auto;
  padding: 2rem;
  border: 1px solid #888;
  width: 90%;
  max-width: 400px;
  border-radius: 10px;
  position: relative;
  animation: fadeInUp 0.5s ease-out;
}
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.modal-content h2 {
  margin-bottom: 1rem;
  text-align: center;
}
.modal-content input[type="email"],
.modal-content input[type="password"] {
  width: 100%;
  padding: 0.8rem;
  margin: 0.5rem 0;
  border: 1px solid #ccc;
  border-radius: 5px;
}
.modal-content button {
  width: 100%;
  padding: 0.8rem;
  background: var(--primary-black);
  color: var(--white);
  border: none;
  border-radius: 5px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.3s ease;
}
.modal-content button:hover {
  background: var(--secondary-black);
}
.close-modal {
  position: absolute;
  right: 1rem;
  top: 1rem;
  font-size: 1.5rem;
  font-weight: bold;
  cursor: pointer;
}
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="nav-content">
      <div class="hamburger"><i class="fas fa-bars"></i></div>
      <a href="index.php" class="logo">Yarlean Nails</a>
      <?php
        if ($loggedIn) {
          echo '<span class="user-welcome">Bienvenido, ' . htmlspecialchars($_SESSION['user']['nombre']) . '</span>';
          echo '<a href="logout.php" class="login-btn">Cerrar Sesión</a>';
        } else {
          echo '<button class="login-btn" id="open-login">Iniciar Sesión</button>';
        }
      ?>
      <div class="cart-icon" id="open-cart">
        <i class="fas fa-shopping-cart"></i>
      </div>
    </div>
  </nav>

  <!-- SIDEBAR DE NAVEGACIÓN -->
  <div class="sidebar">
    <div class="close-btn">&times;</div>
    <div class="sidebar-links">
      <a href="index.php">Inicio</a>
      <a href="productos.php">Productos</a>
      <a href="ofertas.php">Ofertas</a>
      <a href="contacto.php">Contacto</a>
    </div>
  </div>

  <!-- SIDEBAR DEL CARRITO -->
  <div id="cart-sidebar" class="cart-sidebar">
    <div class="close-cart" id="close-cart">&times;</div>
    <h3>Carrito de Compras</h3>
    <ul id="cart-items"></ul>
    <p id="cart-total">Total: $0.00</p>
    <button id="checkout-btn">Finalizar Compras</button>
  </div>

  <!-- CONTENEDOR PRINCIPAL: FILTRO Y PRODUCTOS -->
  <div class="main-content">
    <!-- Filtro de Categorías -->
    <aside class="filter">
      <h3>Categorías</h3>
      <ul>
        <?php while($cat = $resultCategorias->fetch_assoc()): ?>
          <li>
            <a href="productos.php?categoria=<?php echo urlencode($cat['nombre']); ?>">
              <?php echo htmlspecialchars($cat['nombre']); ?>
            </a>
          </li>
        <?php endwhile; ?>
      </ul>
    </aside>

    <!-- Sección de Productos -->
    <section class="products">
      <div class="search-container">
        <input type="text" id="search-bar" placeholder="Buscar productos...">
      </div>
      <div class="products-grid">
        <?php
        // Primero se cargan todos los productos en un array
        $products = array();
        if ($resultado->num_rows > 0) {
            while ($row = $resultado->fetch_assoc()) {
                $products[] = $row;
            }
        }
        $totalProducts = count($products);
        // Se determina cuántos productos deben ir en cada una de las 3 pestañas
        $perPage = ($totalProducts > 0) ? ceil($totalProducts / 3) : 1;
        
        // Se recorre el array y se asigna cada producto a una pestaña
        foreach ($products as $index => $row) {
            $displayName = $row['nombre'];
            if ($row['variant_count'] > 0) {
                $detailUrl = "producto_detalle.php?id=" . $row['product_id'] . "&variant=" . $row['first_variant_id'];
            } else {
                $detailUrl = "producto_detalle.php?id=" . $row['product_id'];
            }
            $buttonText = ($row['variant_count'] > 0) ? "Elige tu versión" : "Agregar al Carrito";
            $onClickAction = "window.location.href='$detailUrl'";
            // Se asigna el número de pestaña según el índice
            if ($index < $perPage) {
                $page = 1;
            } elseif ($index < ($perPage * 2)) {
                $page = 2;
            } else {
                $page = 3;
            }
            ?>
            <div class="product-card"
                 data-name="<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES); ?>"
                 data-page="<?php echo $page; ?>"
                 onclick="<?php echo $onClickAction; ?>">
              <img src="<?php echo htmlspecialchars($row['imagen']); ?>" 
                   alt="<?php echo htmlspecialchars($displayName); ?>" 
                   loading="lazy">
              <div class="product-info">
                <h4><?php echo htmlspecialchars($displayName); ?></h4>
                <p class="price">$<?php echo number_format($row['precio'], 2); ?></p>
                <button class="add-to-cart"
                        onclick="event.stopPropagation();
                        <?php 
                          if ($row['variant_count'] > 0) {
                            echo 'window.location.href=\'' . $detailUrl . '\';';
                          } else {
                            echo 'agregarAlCarrito(\'' . $row['product_id'] . '\', \'\', \'' . htmlspecialchars($displayName, ENT_QUOTES) . '\', \'' . $row['precio'] . '\', \'' . $row['stock'] . '\');';
                          }
                        ?>">
                  <?php echo $buttonText; ?>
                </button>
              </div>
            </div>
            <?php
        }
        if ($totalProducts == 0) {
            echo "<p>No hay productos disponibles.</p>";
        }
        ?>
      </div>
      <!-- Paginación con 3 pestañas -->
      <div class="pagination">
        <a href="#" data-action="prev">&laquo;</a>
        <a href="#" data-page="1" class="active">1</a>
        <a href="#" data-page="2">2</a>
        <a href="#" data-page="3">3</a>
        <a href="#" data-action="next">&raquo;</a>
      </div>
    </section>
  </div>

  <!-- MODAL DE INICIO DE SESIÓN -->
  <div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" id="close-login">&times;</span>
      <h2>Iniciar Sesión</h2>
      <form action="login.php" method="POST">
        <input type="email" name="email" placeholder="Correo electrónico" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
      </form>
      <p style="margin-top: 1rem;">
        ¿No tienes cuenta?
        <a href="registro.php" style="color: var(--primary-black); text-decoration: underline;">Crear cuenta nueva</a>
      </p>
    </div>
  </div>

  <script>
    // Variable para saber si el usuario está logueado (para usarla en el checkout)
    var loggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;
  
    document.addEventListener('DOMContentLoaded', function() {
      // Control del sidebar
      const sidebar = document.querySelector('.sidebar');
      const hamburger = document.querySelector('.hamburger');
      const closeSidebarBtn = document.querySelector('.close-btn');
      hamburger.addEventListener('click', () => { sidebar.classList.add('active'); });
      closeSidebarBtn.addEventListener('click', () => { sidebar.classList.remove('active'); });
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.sidebar') &&
            !e.target.closest('.hamburger') &&
            sidebar.classList.contains('active')) {
          sidebar.classList.remove('active');
        }
      });

      // Control del carrito
      const cartSidebar = document.getElementById('cart-sidebar');
      const openCartBtn = document.getElementById('open-cart');
      const closeCartBtn = document.getElementById('close-cart');
      if (openCartBtn) {
        openCartBtn.addEventListener('click', () => {
          loadCartItems();
          cartSidebar.classList.add('active');
        });
      }
      if (closeCartBtn) {
        closeCartBtn.addEventListener('click', () => {
          cartSidebar.classList.remove('active');
        });
      }

      // Paginación: mostrar solo los productos de la pestaña seleccionada
      const paginationLinks = document.querySelectorAll('.pagination a[data-page]');
      function showPage(page) {
        document.querySelectorAll('.product-card').forEach(card => {
          card.style.display = (card.getAttribute('data-page') === page) ? '' : 'none';
        });
        paginationLinks.forEach(link => {
          link.classList.toggle('active', link.getAttribute('data-page') === page);
        });
        // Desplazamiento suave hacia la parte superior
        window.scrollTo({top: 0, behavior: 'smooth'});
      }
      // Se muestra inicialmente la pestaña 1
      showPage("1");

      paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          showPage(this.getAttribute('data-page'));
        });
      });

      // Flechas de paginación (prev y next)
      const prevLink = document.querySelector('.pagination a[data-action="prev"]');
      const nextLink = document.querySelector('.pagination a[data-action="next"]');
      prevLink.addEventListener('click', function(e) {
        e.preventDefault();
        let currentPage = document.querySelector('.pagination a.active').getAttribute('data-page');
        let newPage = parseInt(currentPage) - 1;
        if (newPage < 1) newPage = 1;
        showPage(newPage.toString());
      });
      nextLink.addEventListener('click', function(e) {
        e.preventDefault();
        let currentPage = document.querySelector('.pagination a.active').getAttribute('data-page');
        let newPage = parseInt(currentPage) + 1;
        if (newPage > 3) newPage = 3;
        showPage(newPage.toString());
      });
    });

    function loadCartItems() {
      const cartItemsContainer = document.getElementById('cart-items');
      const cartTotal = document.getElementById('cart-total');
      let cart = JSON.parse(localStorage.getItem('cart')) || [];
      cartItemsContainer.innerHTML = '';
      let total = 0;
      cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        const li = document.createElement('li');
        li.innerHTML = `
          ${item.name} (x${item.quantity}) - $${subtotal.toFixed(2)}
          <button class="delete-button" onclick="removeFromCart(${index})">Eliminar</button>
        `;
        cartItemsContainer.appendChild(li);
      });
      cartTotal.textContent = `Total: $${total.toFixed(2)}`;
    }

    function removeFromCart(index) {
      let cart = JSON.parse(localStorage.getItem('cart')) || [];
      if (index >= 0 && index < cart.length) {
        cart.splice(index, 1);
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCartItems();
      }
    }

    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
      checkoutBtn.addEventListener('click', () => {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (cart.length === 0) {
          alert("Tu carrito está vacío.");
          return;
        }
        // Al finalizar el pedido se requiere login; si no está logueado, se muestra el modal
        if (!loggedIn) {
          openLoginModal();
          return;
        }
        window.location.href = 'pagos.php';
      });
    }

    const searchBar = document.getElementById('search-bar');
    if (searchBar) {
      searchBar.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const products = document.querySelectorAll('.product-card');
        products.forEach(product => {
          const name = product.getAttribute('data-name').toLowerCase();
          product.style.display = name.includes(filter) ? '' : 'none';
        });
      });
    }

    function agregarAlCarrito(productId, variantId, name, price, stock) {
      let cart = JSON.parse(localStorage.getItem('cart')) || [];
      let found = false;
      for (let i = 0; i < cart.length; i++) {
        if (cart[i].product_id == productId && cart[i].variant_id == variantId) {
          cart[i].quantity = (cart[i].quantity || 1) + 1;
          found = true;
          break;
        }
      }
      if (!found) {
        cart.push({
          product_id: productId,
          variant_id: variantId,
          name: name,
          price: parseFloat(price),
          quantity: 1
        });
      }
      localStorage.setItem('cart', JSON.stringify(cart));
      alert("Producto agregado al carrito: " + name);
      if (document.getElementById('cart-sidebar').classList.contains('active')) {
        loadCartItems();
      }
    }

    const loginModal = document.getElementById('loginModal');
    const openLoginBtn = document.getElementById('open-login');
    const closeLoginBtn = document.getElementById('close-login');
    if (openLoginBtn) {
      openLoginBtn.addEventListener('click', () => {
        loginModal.style.display = 'block';
      });
    }
    if (closeLoginBtn) {
      closeLoginBtn.addEventListener('click', () => {
        loginModal.style.display = 'none';
      });
    }
    window.addEventListener('click', (e) => {
      if (e.target === loginModal) {
        loginModal.style.display = 'none';
      }
    });
  </script>
</body>
</html>
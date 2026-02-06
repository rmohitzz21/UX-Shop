// Admin Dashboard JavaScript

// Check admin authentication
function checkAdminAuth() {
  const adminSession = JSON.parse(localStorage.getItem('adminSession'));
  // Note: The primary check is now server-side in admin-dashboard.php
  // This client-side check is just for immediate feedback/redirection
  if (!adminSession || !adminSession.isAdmin) {
    // If we are on the dashboard and local storage is empty, wait to see if server redirects.
    // However, for better UX, we redirect here too.
    window.location.href = 'admin-login.php';
    return false;
  }
  return true;
}

// Admin logout
function handleAdminLogout() {
  if (confirm('Are you sure you want to logout?')) {
    localStorage.removeItem('adminSession');
    window.location.href = 'admin-login.php';
  }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
  if (!checkAdminAuth()) return;
  
  const adminSession = JSON.parse(localStorage.getItem('adminSession'));
  if (adminSession && adminSession.email) {
    document.getElementById('admin-email-display').textContent = adminSession.email;
  }
  
  // Setup tab navigation
  setupTabNavigation();
  
  // Load all data
  loadOverview();
  loadUsers();
  loadProducts();
  loadOrders();
  loadAnalytics();
  setupMediaUpload(); // Initialize media upload logic
});

// Tab Navigation (updated for sidebar)
function setupTabNavigation() {
  // Sidebar navigation items
  document.querySelectorAll('.sidebar-nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      const tab = this.dataset.tab;
      
      // Update sidebar nav
      document.querySelectorAll('.sidebar-nav-item').forEach(nav => nav.classList.remove('active'));
      this.classList.add('active');
      
      // Update tabs
      document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
      document.getElementById(tab + '-tab').classList.add('active');
      
      // Reload data for active tab
      if (tab === 'overview') loadOverview();
      else if (tab === 'users') loadUsers();
      else if (tab === 'products') loadProducts();
      else if (tab === 'orders') loadOrders();
      else if (tab === 'analytics') loadAnalytics();
      
      // Close mobile sidebar
      if (window.innerWidth <= 1024) {
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      }
    });
  });
}

// Load Overview Data
async function loadOverview() {
  const users = await fetchUsers();
  const products = await fetchProducts();
  
  // Fetch orders from API
  let orders = [];
  try {
    const response = await fetch('../api/admin/order/list.php');
    orders = await response.json();
  } catch (e) {
    console.error("Error fetching orders for overview:", e);
  }
  
  // Merge with localStorage orders for total count (if any)
  const localOrders = getAllOrders();
  const totalOrdersCount = Math.max(orders.length, localOrders.length);
  
  // Update stats
  document.getElementById('stat-total-users').textContent = users.length;
  document.getElementById('stat-total-products').textContent = products.length;
  document.getElementById('stat-total-orders').textContent = orders.length; // Priority to DB
  
  const totalRevenue = orders.reduce((sum, order) => sum + parseFloat(order.total || 0), 0);
  document.getElementById('stat-total-revenue').textContent = `$${totalRevenue.toLocaleString()}`;
  
  // Load recent orders
  const recentOrders = orders.slice(0, 5); // list.php already orders by DESC
  const recentOrdersTable = document.getElementById('recent-orders-table');
  
  if (recentOrders.length === 0) {
    recentOrdersTable.innerHTML = '<tr><td colspan="5" class="empty-state">No orders yet</td></tr>';
    return;
  }
  
  recentOrdersTable.innerHTML = recentOrders.map(order => {
    const orderDate = new Date(order.created_at || Date.now());
    const statusBadge = getStatusBadge(order.status || 'Pending');
    return `
      <tr>
        <td>${order.order_number || 'N/A'}</td>
        <td>${getOrderCustomerName(order)}</td>
        <td>${orderDate.toLocaleDateString()}</td>
        <td>$${parseFloat(order.total || 0).toLocaleString()}</td>
        <td>${statusBadge}</td>
      </tr>
    `;
  }).join('');
}

// Load Users
// Load Users
async function loadUsers() {
  const usersTable = document.getElementById('users-table');
  if (!usersTable) return;

  try {
    const apiUsers = await fetchUsers();
    const localUsers = getAllUsers(); // From localStorage
    
    // Merge users (deduplicate by email, prefer API data)
    const userMap = new Map();
    // Start with local users
    localUsers.forEach(u => userMap.set(u.email, u));
    // Merge API users (overwriting local with fresh DB data like is_blocked)
    apiUsers.forEach(u => {
        if (userMap.has(u.email)) {
            userMap.set(u.email, { ...userMap.get(u.email), ...u });
        } else {
            userMap.set(u.email, u);
        }
    });

    const allUsers = Array.from(userMap.values());
    
    if (allUsers.length === 0) {
      usersTable.innerHTML = '<tr><td colspan="7" class="empty-state">No users found</td></tr>';
      return;
    }
    
    usersTable.innerHTML = allUsers.map(user => {
      // Handle various date formats
      let dateString = 'N/A';
      if (user.created_at) {
        dateString = new Date(user.created_at).toLocaleDateString();
      } else if (user.loginTime) {
        dateString = new Date(user.loginTime).toLocaleDateString();
      }
      
      // Use database count if available, otherwise fallback to matching by email in localStorage (for legacy)
      const dbOrderCount = user.order_count || 0;
      const localOrders = getAllOrders().filter(o => getOrderEmail(o) === user.email);
      const ordersCount = Math.max(dbOrderCount, localOrders.length);
      
      // API returns first_name/last_name (snake_case), localStorage might use camelCase
      const firstName = user.first_name || user.firstName || '';
      const lastName = user.last_name || user.lastName || '';
      const fullName = `${firstName} ${lastName}`.trim();
      
      const displayName = user.name || fullName || user.username || 'N/A';
 
      const isBlocked = ((user.is_blocked == 1) || (user.is_blocked === '1'));
      const statusBadge = isBlocked 
        ? '<span class="badge badge-danger">Blocked</span>' 
        : '<span class="badge badge-success">Active</span>';
 
      const userId = user.id;
      const blockButton = userId ? `
        <button 
          type="button"
          class="btn-small ${isBlocked ? 'btn-success' : 'btn-delete'}" 
          onclick="toggleUserBlock(${userId}, this, ${isBlocked})">
          ${isBlocked ? 'Unblock' : 'Block'}
        </button>
      ` : '<span style="color:#aaa; font-size:0.8rem;">Guest/No ID</span>';
      
      return `
        <tr>
          <td>${displayName}</td>
          <td>${user.email || 'N/A'}</td>
          <td>${user.phone || 'N/A'}</td>
          <td>${dateString}</td>
          <td>${ordersCount}</td>
          <td>${statusBadge}</td>
          <td>${blockButton}</td>
        </tr>
      `;
    }).join('');
  } catch (error) {
    console.error('Error loading users:', error);
    usersTable.innerHTML = '<tr><td colspan="6" class="empty-state">Error loading users</td></tr>';
  }
}

async function fetchUsers(){
  try {
    const response = await fetch('../api/admin/user/list.php');

    if(!response.ok){
      throw new Error(`HTTP error ! status : ${response.status}`);
    }

    const result = await response.json();
    return result.data || [];
  } catch (error) {
    console.error('Error fetching users : ',error);
    return [];
  }
}

// Load Products
async function loadProducts() {
  const productsTable = document.getElementById('products-table');
  productsTable.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem;">Loading products...</td></tr>';
  
  try {
    const products = await fetchProducts();
    
    if (products.length === 0) {
      productsTable.innerHTML = '<tr><td colspan="7" class="empty-state">No products found</td></tr>';
      return;
    }
    
    productsTable.innerHTML = products.map(product => {
      const category = product.category || 'Uncategorized';
      const categoryBadge = `<span class="badge badge-info">${category}</span>`;
      
      const isChecked = (product.is_active == 1) ? 'checked' : '';
      
      return `
        <tr>
          <td><img src="${product.image ? '../' + product.image : '../img/sticker.webp'}" alt="${product.name}" class="product-image" onerror="this.src='../img/sticker.webp'"></td>
          <td>${product.name || 'N/A'}</td>
          <td>${categoryBadge}</td>
          <td>$${parseFloat(product.price || 0).toLocaleString()}</td>
          <td>${product.stock || 0}</td>
          <td>★ ${product.rating || '0.0'}</td>
          <td>
            <label class="switch">
              <input type="checkbox" ${isChecked} onchange="toggleProductStatus(${product.id}, this)">
              <span class="slider round"></span>
            </label>
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn-small btn-edit" onclick="editProduct('${product.id}')">Edit</button>
              <button class="btn-small btn-delete" onclick="deleteProduct('${product.id}')">Delete</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  } catch (error) {
    console.error('Error loading products:', error);
    productsTable.innerHTML = '<tr><td colspan="7" class="empty-state" style="color:red;">Error loading products. Please try again later.</td></tr>';
  }
}

// Fetch products from API
async function fetchProducts() {
  try {
    const response = await fetch('../api/admin/product/list.php');
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    const result = await response.json();
    return result.data || [];
  } catch (error) {
    console.error('Fetch products error:', error);
    return [];
  }
}

// Load Orders
// Load Orders (from API)
async function loadOrders() {
  const ordersTable = document.getElementById('orders-table');
  ordersTable.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">Loading orders from database...</td></tr>';
  
  try {
      const response = await fetch('../api/admin/order/list.php');
      const orders = await response.json();
      
      if (!Array.isArray(orders)) {
          throw new Error("Invalid response format");
      }

      if (orders.length === 0) {
        ordersTable.innerHTML = '<tr><td colspan="8" class="empty-state">No orders found</td></tr>';
        return;
      }
      
      ordersTable.innerHTML = orders.map(order => {
        const orderDate = new Date(order.created_at || Date.now());
        const statusBadge = getStatusBadge(order.status || 'Pending');
        const itemsCount = order.items_count || 0;
        const paymentMethod = order.payment_method || 'card';
        const paymentBadge = `<span class="badge badge-info">${paymentMethod.toUpperCase()}</span>`;
        
        return `
          <tr>
            <td>${order.order_number || 'N/A'}</td>
            <td>${getOrderCustomerName(order)}</td>
            <td>${itemsCount} item(s)</td>
            <td>${orderDate.toLocaleDateString()}</td>
            <td>$${parseFloat(order.total || 0).toLocaleString()}</td>
            <td>${paymentBadge}</td>
            <td>${statusBadge}</td>
            <td>
              <div class="action-buttons">
                <button class="btn-small btn-edit" onclick="viewOrder('${order.id}')">View</button>
                <button class="btn-small btn-edit" onclick="updateOrderStatus('${order.order_number}', '${order.status}', '${getOrderCustomerName(order).replace(/'/g, "\\'")}')">Update</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
      
  } catch(error) {
      console.error("Error loading orders:", error);
      ordersTable.innerHTML = '<tr><td colspan="8" class="empty-state" style="color:red">Failed to load orders from server.</td></tr>';
  }
}

// Load Analytics
async function loadAnalytics() {
  // Fetch orders from API
  let orders = [];
  try {
    const response = await fetch('../api/admin/order/list.php');
    orders = await response.json();
  } catch (e) {
    console.error("Error fetching orders for analytics:", e);
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  // Today's revenue
  const todayOrders = orders.filter(o => {
    const orderDate = new Date(o.created_at || Date.now());
    orderDate.setHours(0, 0, 0, 0);
    return orderDate.getTime() === today.getTime();
  });
  const todayRevenue = todayOrders.reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
  document.getElementById('analytics-today-revenue').textContent = `$${todayRevenue.toLocaleString()}`;
  
  // This month's revenue
  const thisMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  const monthOrders = orders.filter(o => {
    const orderDate = new Date(o.created_at || Date.now());
    return orderDate >= thisMonth;
  });
  const monthRevenue = monthOrders.reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
  document.getElementById('analytics-month-revenue').textContent = `$${monthRevenue.toLocaleString()}`;
  
  // Average order value
  const avgOrder = orders.length > 0 ? orders.reduce((sum, o) => sum + parseFloat(o.total || 0), 0) / orders.length : 0;
  document.getElementById('analytics-avg-order').textContent = `$${Math.round(avgOrder).toLocaleString()}`;
  
  // Conversion rate (simplified - would need actual visitor data)
  const users = await fetchUsers();
  const conversionRate = users.length > 0 ? (orders.length / users.length * 100).toFixed(1) : 0;
  document.getElementById('analytics-conversion').textContent = `${conversionRate}%`;
  
  // Top selling products - Note: This requires item details. 
  // For a full implementation, we'd need another API or parse JSON shipping_address if items are stored there,
  // but usually top products are calculated from order_items in DB.
  // For now, let's show "Loading..." or a message if we can't get items directly from list.php
  const topProductsTable = document.getElementById('top-products-table');
  topProductsTable.innerHTML = '<tr><td colspan="4" class="empty-state">Detailed product analytics coming soon from DB</td></tr>';
}

// Helper Functions
function getAllUsers() {
  // Get users from localStorage (from signup/signin)
  const users = [];
  const userSession = JSON.parse(localStorage.getItem('userSession'));
  if (userSession && userSession.email) {
    users.push(userSession);
  }
  
  // Also get users from orders
  const orders = getAllOrders();
  orders.forEach(order => {
    if (order.shipping && order.shipping.email) {
      const existingUser = users.find(u => u.email === order.shipping.email);
      if (!existingUser) {
        users.push({
          email: order.shipping.email,
          name: `${order.shipping.firstName || ''} ${order.shipping.lastName || ''}`.trim(),
          firstName: order.shipping.firstName,
          lastName: order.shipping.lastName,
          phone: order.shipping.phone
        });
      }
    }
  });
  
  return users;
}



function getAllOrders() {
  return JSON.parse(localStorage.getItem('orders')) || [];
}

function getOrderCustomerName(order) {
  // If registered user info is present from the JOIN
  if (order.first_name || order.last_name) {
      const name = `${order.first_name || ''} ${order.last_name || ''}`.trim();
      if (name) return name;
  }
  
  // Fallback to shipping_address (Guest)
  let shipping = order.shipping_address;
  if (typeof shipping === 'string') {
      try { shipping = JSON.parse(shipping); } catch(e) { console.error("Error parsing shipping", e); }
  }
  
  if (shipping) {
      const guestName = `${shipping.firstName || ''} ${shipping.lastName || ''}`.trim();
      if (guestName) return guestName + ' (Guest)';
  }
  
  return 'Guest';
}

function getOrderEmail(order) {
  // Registered user email
  if (order.email) return order.email;
  
  // Guest email from shipping details
  let shipping = order.shipping_address;
  if (typeof shipping === 'string') {
      try { shipping = JSON.parse(shipping); } catch(e) {}
  }
  
  if (shipping && shipping.email) return shipping.email;
  
  return 'N/A';
}

function getStatusBadge(status) {
  const badges = {
    'Pending': '<span class="badge badge-warning">Pending</span>',
    'Processing': '<span class="badge badge-info">Processing</span>',
    'Shipped': '<span class="badge badge-info">Shipped</span>',
    'Delivered': '<span class="badge badge-success">Delivered</span>',
    'Cancelled': '<span class="badge badge-danger">Cancelled</span>'
  };
  return badges[status] || '<span class="badge badge-warning">Pending</span>';
}

// Filter Functions
function filterUsers() {
  const search = document.getElementById('user-search').value.toLowerCase();
  const rows = document.querySelectorAll('#users-table tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(search) ? '' : 'none';
  });
}

function filterProducts() {
  const search = document.getElementById('product-search').value.toLowerCase();
  const category = document.getElementById('product-category-filter').value.toLowerCase();
  const rows = document.querySelectorAll('#products-table tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    const matchesSearch = text.includes(search);
    const matchesCategory = !category || text.includes(category);
    row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
  });
}

function filterOrders() {
  const search = document.getElementById('order-search').value.toLowerCase();
  const status = document.getElementById('order-status-filter').value.toLowerCase();
  const rows = document.querySelectorAll('#orders-table tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    const matchesSearch = text.includes(search);
    const matchesStatus = !status || text.includes(status);
    row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
  });
}

// Action Functions
// Edit Product Functions
async function editProduct(productId) {
  try {
      // Fetch product details
      const response = await fetch(`../api/admin/product/get.php?id=${productId}`);
      const result = await response.json();
      
      if (result.status === 'success' && result.data) {
          const p = result.data;
          // Populate form
          const setVal = (id, val) => {
             const el = document.getElementById(id);
             if(el) el.value = val;
          };
          
          setVal('edit-product-id', p.id);
          setVal('edit-product-name', p.name);
          setVal('edit-product-category', p.category);
          setVal('edit-product-price', p.price);
          setVal('edit-product-old-price', p.old_price || '');
          setVal('edit-product-stock', p.stock || 0);
          setVal('edit-product-rating', p.rating || 0);
          setVal('edit-product-description', p.description || '');
          
          const previewEl = document.getElementById('current-image-preview');
          if (previewEl) {
              if (p.image) {
                  const imagePath = p.image.startsWith('img/') ? '../' + p.image : p.image;
                  previewEl.innerHTML = `<div style="display: flex; align-items: center; gap: 10px;"><img src="${imagePath}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"> <span>Current Image</span></div>`;
              } else {
                  previewEl.innerHTML = 'No image currently set.';
              }
          }
          
          openEditProductModal();
      } else {
          alert('Failed to fetch product details.');
      }
  } catch (e) {
      console.error(e);
      alert('Error fetching product details.');
  }
}

function openEditProductModal() {
  const modal = document.getElementById('edit-product-modal-overlay');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeEditProductModal() {
  const modal = document.getElementById('edit-product-modal-overlay');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    // Reset form
    document.getElementById('edit-product-form').reset();
    document.getElementById('current-image-preview').innerHTML = '';
  }
}

async function handleUpdateProduct(event) {
    event.preventDefault();
    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    
    btn.disabled = true;
    btn.innerText = 'Saving...';
    
    try {
        const formData = new FormData(form);
        const response = await fetch('../api/admin/product/update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            alert('Product updated successfully');
            closeEditProductModal();
            loadProducts(); // Reload list
            loadOverview(); // Reload stats
        } else {
            alert(result.message || 'Update failed');
        }
    } catch (e) {
        alert('Error updating product: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

async function toggleProductStatus(productId, checkbox) {
  const isActive = checkbox.checked ? 1 : 0;
  
  try {
    const response = await fetch('../api/admin/product/toggle_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: productId, is_active: isActive })
    });
    
    const result = await response.json();
    
    if (result.status !== 'success') {
      alert('Failed to update status');
      checkbox.checked = !checkbox.checked; // Revert
    }
  } catch (error) {
    console.error('Error updating product status:', error);
    alert('Error updating status');
    checkbox.checked = !checkbox.checked; // Revert
  }
}



async function deleteProduct(productId) {
  if (!confirm('Are you sure you want to delete this product?')) return;

  // Attempt to find the button to show loading state (optional best effort)
  const btn = document.querySelector(`button[onclick="deleteProduct('${productId}')"]`);
  let originalText = 'Delete';
  if (btn) {
    originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Deleting...';
  }

  try {
    const formData = new FormData();
    formData.append('id', productId);

    const response = await fetch('../api/admin/product/delete.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.status === 'success') {
      // Refresh data
      loadProducts();
      loadOverview();
    } else {
      alert(result.message || 'Delete failed');
    }

  } catch (e) {
    alert('Error deleting product: ' + e.message);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerText = originalText;
    }
  }
}



async function viewOrder(orderId) {
  const contentEl = document.getElementById('order-details-content');
  contentEl.innerHTML = '<div style="text-align:center; padding: 2rem;">Loading order details...</div>';
  openOrderDetailsModal();

  try {
    const response = await fetch(`../api/admin/order/get_details.php?id=${orderId}`);
    const result = await response.json();

    if (result.status === 'success') {
      const order = result.data;
      let shipping = {};
      try {
        shipping = typeof order.shipping_address === 'string' ? JSON.parse(order.shipping_address) : order.shipping_address;
      } catch (e) {
        console.error("Error parsing shipping address", e);
      }

      const itemsHtml = order.items.map(item => `
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <img src="../${item.image || 'img/sticker.webp'}" alt="${item.name}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
              <div>
                <div style="font-weight: 600;">${item.name}</div>
                ${item.size ? `<div style="font-size: 0.75rem; color: #666;">Size: ${item.size}</div>` : ''}
              </div>
            </div>
          </td>
          <td>$${parseFloat(item.price).toFixed(2)}</td>
          <td>${item.quantity}</td>
          <td style="text-align: right;">$${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
        </tr>
      `).join('');

      contentEl.innerHTML = `
        <div class="order-details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
          <div>
            <h3 style="font-size: 0.9rem; text-transform: uppercase; color: #666; margin-bottom: 0.5rem;">Customer Information</h3>
            <p><strong>Name:</strong> ${order.first_name ? `${order.first_name} ${order.last_name}` : (shipping.firstName ? `${shipping.firstName} ${shipping.lastName} (Guest)` : 'Guest')}</p>
            <p><strong>Email:</strong> ${order.email || shipping.email || 'N/A'}</p>
            <p><strong>Phone:</strong> ${order.phone || shipping.phone || 'N/A'}</p>
          </div>
          <div>
            <h3 style="font-size: 0.9rem; text-transform: uppercase; color: #666; margin-bottom: 0.5rem;">Order Information</h3>
            <p><strong>Order ID:</strong> ${order.order_number}</p>
            <p><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</p>
            <p><strong>Status:</strong> ${getStatusBadge(order.status)}</p>
            <p><strong>Payment:</strong> <span class="badge badge-info">${order.payment_method.toUpperCase()}</span></p>
          </div>
        </div>

        <div style="margin-bottom: 2rem;">
          <h3 style="font-size: 0.9rem; text-transform: uppercase; color: #666; margin-bottom: 0.5rem;">Shipping Address</h3>
          <p>${shipping.address || 'N/A'}, ${shipping.apartment || ''}</p>
          <p>${shipping.city || 'N/A'}, ${shipping.postalCode || ''}</p>
          <p>${shipping.country || 'N/A'}</p>
        </div>

        <div class="order-items-table">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid var(--admin-border);">
                <th style="padding: 0.5rem 0; text-align: left;">Product</th>
                <th style="padding: 0.5rem 0; text-align: left;">Price</th>
                <th style="padding: 0.5rem 0; text-align: left;">Qty</th>
                <th style="padding: 0.5rem 0; text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody>
              ${itemsHtml}
            </tbody>
            <tfoot>
              <tr style="border-top: 2px solid var(--admin-border); font-weight: 700;">
                <td colspan="3" style="padding: 1rem 0; text-align: right;">Total Amount:</td>
                <td style="padding: 1rem 0; text-align: right; color: var(--admin-accent); font-size: 1.2rem;">$${parseFloat(order.total).toLocaleString()}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      `;
    } else {
      contentEl.innerHTML = `<div style="text-align:center; padding: 2rem; color: red;">Error: ${result.message}</div>`;
    }
  } catch (error) {
    console.error("Error fetching order details", error);
    contentEl.innerHTML = `<div style="text-align:center; padding: 2rem; color: red;">Failed to load order details.</div>`;
  }
}

function openOrderDetailsModal() {
  const modal = document.getElementById('order-details-modal-overlay');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeOrderDetailsModal() {
  const modal = document.getElementById('order-details-modal-overlay');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Modal UI Functions (UI only - no logic)
function updateOrderStatus(orderNumber, currentStatus, customerName) {
  document.getElementById('modal-order-number').textContent = orderNumber;
  document.getElementById('modal-order-customer').textContent = customerName;
  document.getElementById('modal-current-status').innerHTML = getStatusBadge(currentStatus);
  document.getElementById('status-select').value = currentStatus;
  
  openStatusModal();
}

function openStatusModal() {
  const modal = document.getElementById('status-modal-overlay');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
  }
}

function closeStatusModal() {
  const modal = document.getElementById('status-modal-overlay');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = ''; // Restore scrolling
  }
}

async function confirmStatusUpdate() {
  const orderNumber = document.getElementById('modal-order-number').textContent;
  const newStatus = document.getElementById('status-select').value;
  const btn = document.querySelector('#status-modal-overlay .btn-primary');
  
  if (!orderNumber || !newStatus) return;
  
  const originalText = btn.innerText;
  btn.innerText = 'Updating...';
  btn.disabled = true;

  try {
    const response = await fetch('../api/admin/order/update_status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_number: orderNumber,
        status: newStatus
      })
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      // Close modal
      closeStatusModal();
      
      // Refresh orders table
      loadOrders();
      loadOverview(); // Update stats if needed
      
      // Show toast or alert? Alert for now to be safe
      // alert('Order status updated successfully');
    } else {
      alert(result.message || 'Failed to update status');
    }
  } catch (error) {
    console.error('Error updating status:', error);
    alert('An error occurred while updating status');
  } finally {
    if (btn) {
      btn.innerText = originalText;
      btn.disabled = false;
    }
  }
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeStatusModal();
  }
});


async function toggleUserBlock(userId, btn, currentIsBlocked) {
  const action = currentIsBlocked ? 'unblock' : 'block';
  
  if (!confirm(`Are you sure you want to ${action} this user?`)) {
    return;
  }

  const originalText = btn.innerText;
  btn.innerText = '...';
  btn.disabled = true;

  try {
    const response = await fetch('../api/admin/user/block.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: userId,
        action: action
      })
    });

    const result = await response.json();

    if (result.status === 'success') {
      const newBlockedState = !currentIsBlocked;
      btn.innerText = newBlockedState ? 'Unblock' : 'Block';
      btn.className = `btn-small ${newBlockedState ? 'btn-success' : 'btn-delete'}`;
      btn.setAttribute('onclick', `toggleUserBlock(${userId}, this, ${newBlockedState})`);
    } else {
      alert(result.message || `Failed to ${action} user`);
      btn.innerText = originalText;
    }
  } catch (error) {
    console.error('Error toggling block status:', error);
    alert('An error occurred while updating status');
    btn.innerText = originalText;
  } finally {
    btn.disabled = false;
  }
}

// ==================== PRODUCT MEDIA & CREATE LOGIC ====================

let selectedProductFiles = [];

function setupMediaUpload() {
  // This assumes you have a form with id="create-product-form" and input id="product-media-input"
  const mediaInput = document.getElementById('product-media-input');
  const dropZone = document.getElementById('media-upload-area');

  if (mediaInput) {
    // Ensure input allows multiple files
    mediaInput.setAttribute('multiple', 'multiple');
    mediaInput.setAttribute('name', 'media[]');
    mediaInput.addEventListener('change', handleMediaSelect);
  }
  
  // Optional: Drag and drop support
  if (dropZone && mediaInput) {
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.style.borderColor = 'var(--accent)';
      dropZone.style.background = 'rgba(111, 75, 255, 0.05)';
    });
    
    dropZone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      dropZone.style.borderColor = '';
      dropZone.style.background = '';
    });
    
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.style.borderColor = '';
      dropZone.style.background = '';
      handleFiles(e.dataTransfer.files);
    });

    // Click to upload
    dropZone.addEventListener('click', (e) => {
      if (e.target !== mediaInput) {
        mediaInput.click();
      }
    });
  }
}

function handleMediaSelect(event) {
  handleFiles(event.target.files);
}

function handleFiles(files) {
  const MAX_SIZE = 15 * 1024 * 1024; // 15MB
  const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'pdf', 'doc', 'docx'];

  Array.from(files).forEach(file => {
    const ext = file.name.split('.').pop().toLowerCase();
    
    if (!ALLOWED_EXTS.includes(ext)) {
      alert(`Skipped "${file.name}": File type not supported.`);
      return;
    }

    if (file.size > MAX_SIZE) {
      alert(`Skipped "${file.name}": File size exceeds 15MB.`);
      return;
    }

    // Prevent duplicates based on name and size
    if (!selectedProductFiles.some(f => f.name === file.name && f.size === file.size)) {
      selectedProductFiles.push(file);
    }
  });
  updateMediaUI();
}

function removeFile(index) {
  selectedProductFiles.splice(index, 1);
  updateMediaUI();
}

function updateMediaUI() {
  const previewContainer = document.getElementById('media-preview-grid');
  const mediaInput = document.getElementById('product-media-input');
  
  if (!previewContainer || !mediaInput) return;
  
  // Update the input files property using DataTransfer
  const dataTransfer = new DataTransfer();
  selectedProductFiles.forEach(file => dataTransfer.items.add(file));
  mediaInput.files = dataTransfer.files;

  // Render previews
  previewContainer.innerHTML = ''; // Clear existing previews

  selectedProductFiles.forEach((file, index) => {
    const item = document.createElement('div');
    item.className = 'media-preview-item';
    
    const removeBtn = document.createElement('div');
    removeBtn.className = 'media-remove-btn';
    removeBtn.innerHTML = '&times;';
    removeBtn.onclick = (e) => {
      e.stopPropagation();
      removeFile(index);
    };

    let content = '';
    const objectUrl = URL.createObjectURL(file);

    if (file.type.startsWith('image/')) {
      content = `<img src="${objectUrl}" alt="Preview">`;
    } else if (file.type.startsWith('video/')) {
      content = `<video src="${objectUrl}" controls></video><span class="media-type-badge">Video</span>`;
    } else {
      // Document placeholder
      content = `<div class="media-doc-preview">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    <span style="font-size:10px;margin-top:4px;text-align:center;word-break:break-all;">${file.name}</span>
                   </div>`;
    }
    
    // Add size badge
    const sizeStr = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
    content += `<span class="media-size-badge">${sizeStr}</span>`;
    
    item.innerHTML = content;
    item.appendChild(removeBtn);
    previewContainer.appendChild(item);
  });
}

async function handleCreateProduct(event) {
  event.preventDefault();
  const form = event.target;
  const btn = form.querySelector('button[type="submit"]');
  const originalText = btn.innerText;

  btn.disabled = true;
  btn.innerText = 'Creating...';

  try {
    const formData = new FormData(form);
    
    const response = await fetch('../api/admin/product/create.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.status === 'success') {
      alert('Product created successfully!');
      form.reset();
      selectedProductFiles = []; // Clear file array
      updateMediaUI(); // Clear UI
      loadProducts(); // Refresh list
      loadOverview();
    } else {
      alert(result.message || 'Failed to create product');
    }
  } catch (error) {
    console.error('Error creating product:', error);
    alert('An error occurred');
  } finally {
    btn.disabled = false;
    btn.innerText = originalText;
  }
}

// Make functions globally available
window.handleAdminLogout = handleAdminLogout;
window.filterUsers = filterUsers;
window.filterProducts = filterProducts;
window.filterOrders = filterOrders;
window.editProduct = editProduct;
window.closeEditProductModal = closeEditProductModal;
window.handleUpdateProduct = handleUpdateProduct;
window.deleteProduct = deleteProduct;
window.viewOrder = viewOrder;
window.updateOrderStatus = updateOrderStatus;
window.openStatusModal = openStatusModal;
window.closeStatusModal = closeStatusModal;
window.confirmStatusUpdate = confirmStatusUpdate;
window.toggleUserBlock = toggleUserBlock;
window.toggleProductStatus = toggleProductStatus;
window.handleMediaSelect = handleMediaSelect;
window.handleCreateProduct = handleCreateProduct;

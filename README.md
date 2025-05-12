# laravel-web-app

# 🎮 PC Game Key Online Store

This is a full-stack web application for purchasing PC game keys. The system allows users to browse and filter games, add them to a cart, and securely complete purchases via Stripe. Game data is fetched from the RAWG API, and real-time pricing is retrieved from the CheapShark API.

---

## ✅ Core Features

### 🧾 Account & User Management
- User registration, login, and logout
- Edit account information (email, password, username)
- Admin dashboard with user management (block, edit, delete)

### 🎮 Game Catalog
- Game list with search and filtering by title, genre, price, developer
- Game details page (description, screenshots, release date, genres)
- Prices retrieved from CheapShark API (including competitor price comparison)
- Manual “Update Price” button to fetch latest deal from API

### 🛒 Shopping & Payments
- Add/remove games to/from shopping cart
- Stripe integration for secure payments
- Key reservation system: key is reserved for 10 minutes when added to cart
- Refund request option after purchase
- Order history and status tracking
- Confirmation page after successful payment

### 💬 Reviews & Wishlist
- Game rating and review system (1–5 stars + comment)
- Wishlist functionality (save and remove games)
- Admin moderation of reviews

### 🧰 Admin Controls
- Full CRUD for games and users
- View and manage orders and payments
- Handle support tickets
- Update game prices manually

---

## 🛠️ Technologies Used

- **Laravel (PHP):** Robust MVC backend framework
- **Blade Templates:** Reusable dynamic front-end rendering
- **MySQL:** Database for users, orders, games, reviews
- **Bootstrap:** Responsive front-end layout and UI
- **Stripe API:** Secure and fast payment processing
- **RAWG API:** Game metadata
- **CheapShark API:** Live game prices from various stores

---

## 🔐 Security & Reliability

- Passwords hashed with Bcrypt
- CSRF protection
- Stripe ensures safe payment handling
- Database validation on all user inputs
- Game keys reserved for 10 minutes to avoid overselling
- Laravel’s built-in exception and error handling

---

## 📁 Database Structure (Simplified)

- `users` – login, contact info, roles
- `games` – title, price, description, developer, genre
- `orders`, `order_items` – tracks purchased games
- `favorites` – wishlist functionality
- `reviews` – user feedback per game
- `keys` – game key inventory with reservation timestamps

---

## 🧭 User Roles

- **Guest**: Browse games, search, view pricing
- **User**: Add to cart, purchase, write reviews, manage wishlist, request refunds
- **Admin**: Full CRUD control over all entities, moderate content, update prices

---

## 🗂️ Additional Features

- 🔄 Key reservation: game key is locked for 10 minutes in cart
- 💸 Refund request logic after purchase
- 🏷️ Price comparison with other stores (competitor prices)
- 🔃 Manual price update via “Update price” button

---

## 📎 Disclaimer

- Only PC games are supported due to API constraints
- RAWG and CheapShark APIs are used under their respective usage policies
- Payment processing relies entirely on Stripe

---

## 👨‍💻 Author

**Giedrius Dauknys** – 3rd year IT student  
Vilnius University  
📧 giedrius.dauknys@stud.vu.lt  
🔗 [Portfolio](https://GiedriusDk.github.io) | [GitHub](https://github.com/GiedriusDk)


# 🎯 Freelance Services Marketplace

## 📌 Project Overview
Freelance Services Marketplace is a database-driven web application that connects **Clients** with **Freelancers** offering professional services such as web development, design, writing, marketing, and more.
The system simulates a real freelancing platform (similar to Fiverr/Upwork) and was developed using core web technologies only.

---

## 👥 User Roles

### 👤 Client
- Register & Login
- Browse & Search Services
- View Service Details
- Add Services to Cart
- Complete Checkout
- Place Orders
- Manage Orders

### 👨‍💻 Freelancer
- Register & Login
- Manage Profile
- Create Services (3-step process)
- Upload Service Images
- Edit / Activate / Deactivate Services
- Mark Services as Featured (max 3)
- Manage Orders & Deliver Work

---

## 🛠 Technologies Used

- **HTML5** – Page structure
- **CSS3** – Styling (Flexbox & Grid)
- **PHP** – Server-side logic & sessions
- **MySQL** – Database
- **PDO** – Secure database connection


## 🔐 Security Features

- Password hashing
- Prepared statements (PDO)
- Role-based access control
- Session management
- Account lock after multiple failed login attempts

---

## 🛍 Core Features

### ✅ Authentication System
- User Registration with validation
- Login system with session handling
- Role-based redirection

### 🧾 Service Management
- Create Service (Basic Info → Upload Images → Review)
- Unique 10-digit Service ID
- Status: Active / Inactive
- Featured toggle (max 3 per freelancer)

### 🔎 Browse & Search
- Keyword search
- Category filtering
- Sorting (Price / Date)
- Featured services section
- Pagination

### 🛒 Shopping Cart (OOP)
- Cart stores Service objects
- Price locked when added
- 5% service fee calculation
- No duplicate services allowed
- Session-based cart

### 💳 Checkout System
- 3-step checkout process
- One service = one order
- Requirement file uploads
- Transaction ID generation
- Cart cleared after successful order

### 📦 Order Management
- Track order status
- Upload deliveries
- Request revisions
- Mark orders as completed
- Leave reviews

---

## 🗄 Database Structure

Main Tables:
- Users
- Services
- Service Images
- Orders
- File Attachments
- Reviews
- Categories & Subcategories

---

## 📂 Project Structure

/uploads/
/profiles/
/services/
/orders/

db.php.inc
index.php
browse-services.php
service-detail.php
create-service.php
edit-service.php
cart.php
checkout.php
my-orders.php
profile.php



## 🎓 Academic Information

- Course: Web Application & Technologies (COMP 334)
- Technologies restricted to: HTML, CSS, PHP, MySQL
- All SQL queries use prepared statements
- Multi-page PHP application
- Individual project

---

## 🚀 Conclusion
This project demonstrates full-stack PHP development, secure authentication, database integration, OOP concepts, file handling, and a complete e-commerce workflow.
<img width="1897" height="909" alt="Screenshot 2026-02-21 002207" src="https://github.com/user-attachments/assets/fecc1dd2-7e32-4617-96fc-5e53f9e841a7" />
<img width="1326" height="910" alt="Screenshot 2026-02-21 002248" src="https://github.com/user-attachments/assets/a8a40b77-2512-4c3b-80b8-1d44101f415f" />
<img width="1586" height="837" alt="Screenshot 2026-02-21 002347" src="https://github.com/user-attachments/assets/bbbff757-058e-407e-85ae-b3f30dc35823" />
<img width="874" height="892" alt="Screenshot 2026-02-21 002418" src="https://github.com/user-attachments/assets/910a48c1-11a9-444f-8914-c3133d32bd56" />
<img width="1574" height="832" alt="Screenshot 2026-02-21 002447" src="https://github.com/user-attachments/assets/184ecf18-f37d-4580-aea2-66a7c759b5c2" />
<img width="1171" height="903" alt="Screenshot 2026-02-21 002547" src="https://github.com/user-attachments/assets/7c3b37f6-bb69-4943-8a73-7ba6e594155d" />
<img width="983" height="856" alt="Screenshot 2026-02-21 002618" src="https://github.com/user-attachments/assets/ccfcd896-8ee3-4920-8401-3eabe3cf7bf3" />
<img width="1583" height="910" alt="Screenshot 2026-02-21 002819" src="https://github.com/user-attachments/assets/6abb9856-6005-4e4f-8ae5-5ed34c0399c4" />
<img width="1583" height="908" alt="Screenshot 2026-02-21 003119" src="https://github.com/user-attachments/assets/bd735ad1-ba33-4786-9083-d512c7181710" />

# Multi-User Hotel Booking System with Concurrency Control

*MSc Computer Science Project — University of Hertfordshire*

A PHP and MySQL web application that solves the classic **double-booking problem**: when two or more users try to book the same hotel room at the same time. This project implements and experimentally compares two industry-standard concurrency control strategies — **pessimistic locking** and **optimistic locking** — to guarantee only one booking ever succeeds, no matter how many users compete for the same room.

---

## 🚀 Features

- User registration and login
- Room search by date range
- Room booking with real-time availability checking
- Booking cancellation
- Booking history ("My Bookings")
- Admin panel for adding new rooms
- Configurable concurrency control strategy (switch between pessimistic and optimistic locking with one line of code)
- An automated concurrency evaluation script that simulates up to 100 simultaneous users booking the same room, and measures the results

## 🛠️ Tech Stack

- **Language:** PHP 8.2
- **Database:** MySQL (InnoDB)
- **Environment:** XAMPP (Apache, MySQL, PHP)
- **Version Control:** Git & GitHub

## 🔒 The Core Problem: Concurrency Control

Imagine two guests both try to book the last available room at the exact same second. Without protection, a system could tell **both** of them "Congratulations, it's yours!" — resulting in a double booking. This project implements and compares two classic solutions:

### Pessimistic Locking
The moment a user starts booking a room, the system locks that room's database row using `SELECT ... FOR UPDATE`. No other user can even read that row until the first transaction completes. Safe, but users queue behind the lock.

### Optimistic Locking
No locking upfront — every user can attempt to book freely. Instead, a hidden `version` number is checked at the moment of saving. If the version has changed since the user loaded the page, someone else already booked it first, and the request is rejected. Faster under normal conditions, but requires a conflict-detection step at the end.

Both strategies are fully implemented, switchable via a single configuration constant in `config/locking.php`.

## 📊 Evaluation Results

An automated test script (`test_concurrency.php`) simulates concurrent booking requests at five different load levels — **2, 10, 20, 50, and 100 simultaneous users** — for both strategies, and measures:

- **Throughput** (requests processed per second)
- **Average response time**
- **Maximum response time** (worst-case wait)
- **Double bookings detected**

| Concurrent Users | Pessimistic Throughput (req/s) | Optimistic Throughput (req/s) | Double Bookings |
|---|---|---|---|
| 2 | 182.48 | 111.73 | 0 |
| 10 | 369.96 | 314.56 | 0 |
| 20 | 379.87 | 297.49 | 0 |
| 50 | 230.41 | 260.58 | 0 |
| 100 | 174.98 | 186.55 | 0 |

**Key finding:** Across every tested load level, **zero double bookings** occurred under either strategy — both approaches successfully preserve data integrity. Pessimistic locking performs better at low-to-moderate load (2–20 users), while optimistic locking scales better once load increases (50–100 users). Pessimistic locking remained more predictable at every level, with a lower worst-case response time throughout.

## 📁 Project Structure

```
hotel_booking/
├── admin/              # Admin panel (add rooms)
├── config/             # Database connection & locking strategy config
├── pages/              # Core pages (login, search, booking, cancellation, etc.)
├── uploads/rooms/      # Room images
├── index.php           # Homepage / room search
├── test_concurrency.php # Automated concurrency evaluation script
└── README.md
```

## ⚙️ Running This Project Locally

1. Install [XAMPP](https://www.apachefriends.org/) and start Apache and MySQL
2. Clone this repository into your `htdocs` folder:
   ```
   git clone https://github.com/Utom2020/hotel-booking-system.git
   ```
3. Import the database schema via phpMyAdmin (`localhost/phpmyadmin`)
4. Update database credentials in `config/db.php` if needed
5. Visit `http://localhost/hotel_booking/index.php` in your browser

To run the concurrency evaluation yourself:
```
http://localhost/hotel_booking/test_concurrency.php
```
Change the number of simulated users by editing `NUM_REQUESTS` at the top of `test_concurrency.php`.

## 📸 Screenshots

*(Add screenshots here — homepage, search results, booking confirmation, admin panel, and the concurrency test results page)*

## 🎓 Academic Context

This project was developed as part of an MSc Computer Science Interim Progress Report and final dissertation at the University of Hertfordshire, drawing on established concurrency control literature including Kung and Robinson (1981), Ramakrishnan and Gehrke (2003), and Menascé and Nakanishi (1982).

## 👤 Author

**Stella Udoh**
MSc Computer Science, University of Hertfordshire
[LinkedIn](https://www.linkedin.com/in/stella-williams-udoh) · [GitHub](https://github.com/Utom2020)

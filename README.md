# Odoo-Hackathon-2025

# 📘 StackIt – Minimal Q&A Forum (PHP + MySQL)

**StackIt** is a clean, minimalistic, and collaborative Q&A platform built with PHP and MySQL. It allows users to ask questions, provide rich answers, vote on contributions, and get notified of relevant activity — all in a lightweight UI.

---
🌐 Live Demo
https://theycotes.com/Stackit/project%202/index.php
---

## 📁 Folder & File Structure

```bash
/StackIt
│
├── api/
│   ├── accept_answer.php            # Marks an answer as accepted
│   ├── get_question_from_answer.php # Fetch question from answer
│   ├── notifications.php            # Notification handler (fetch/send)
│   ├── vote.php                     # Upvote/downvote handler
│
├── assets/
│   ├── css/
│   │   └── style.css                # All page styling
│   ├── js/
│   │   └── main.js                  # Voting, notifications, AJAX
│
├── config/
│   └── database.php                 # DB connection settings
│
├── includes/
│   ├── auth.php                     # Session & auth checker
│   └── functions.php                # Reusable utility functions
│
├── sqlquery/
│   └── 20250712090052_rustic_snow.sql # Sample backup or seed query
│
├── uploads/
│   └── [uploaded images]           # User-uploaded images (from rich text)
│
├── admin.php                        # Admin control panel
├── admin2.php                       # Secondary admin functions
├── ask.php                          # Page to ask a new question
├── index.php                        # Homepage with all questions
├── login.php                        # User login form
├── logout.php                       # Logout and session destroy
├── profile.php                      # User profile and activity
├── question.php                     # View a single question and its answers
├── register.php                     # User registration form
├── upload_image.php                 # Handles image uploads via editor
├── u564191134_stackk.sql            # MySQL dump of the full database
├── project 2 (1).zip                # Optional: zipped version of project
├── README.md                        # This documentation file


🧑‍💻 Tech Stack
Language: PHP (Core PHP)
Database: MySQL
Frontend: HTML, CSS, JavaScript
Rich Text Editor: TinyMCE or CKEditor (for Q/A)
Authentication: PHP Sessions
Notifications: AJAX Polling-based system

✨ Key Features
📝 Ask Questions with title, description (rich text), and tags
🧾 Answer Questions using a rich text editor
👍 Upvote/Downvote answers
✅ Accept the best answer
🏷️ Multi-tag support for questions

🔔 Notification System:
When someone answers your question
When someone comments on your answer
When someone mentions you using @username

🔐 Authentication Flow
register.php – User signup
login.php – Starts session and sets user ID
logout.php – Destroys session and redirects
includes/auth.php – Auth middleware to restrict pages
Admins access moderation via admin.php, admin2.php

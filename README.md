# Mini Accounting Module

A mini accounting module built as a technical assessment project.  
The application demonstrates basic accounting workflows based on double-entry accounting principles using Laravel, Vue, and SQLite.

---

## Tech Stack

Backend: Laravel
Frontend: Vue.js
Database: SQLite

---

## Project Scope

This project was developed based on the following requirements:

1. Add journal entries with multiple debit and credit lines  
2. Validate that total debits equal total credits before saving  
3. Store journal entries with:
   - date
   - description
   - account_id
   - amount
   - type
4. Generate a basic trial balance report grouped by account

---

## Features Implemented

- Create journal entries with multiple line items
- Support both **debit** and **credit** entry lines
- Enforce **double-entry accounting validation**
- Prevent saving unbalanced journal entries
- Store journal entry details
- Display a **trial balance report grouped by account**
- Full-stack integration between Laravel backend and Vue frontend

---

## Business Rules

This module follows the core rule of **double-entry accounting**:

- Every journal entry must contain balanced debit and credit amounts
- The system validates that:

```text
Total Debits = Total Credits
```

## Installation

```bash
git clone https://github.com/lykai1130/Mini-Accounting-Module.git
cd Mini-Accounting-Module

composer install

cp .env.example .env
php artisan key:generate

php artisan migrate --seed

npm install

## Run in different terminal
php artisan serve
npm run dev

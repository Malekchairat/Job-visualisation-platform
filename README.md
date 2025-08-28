# Symfony ETL Job Visualization Platform

This project is a Symfony-based application designed for visualizing ETL (Extract, Transform, Load) job data. It provides insights into the performance, status, and overall health of data processing pipelines, leveraging a MySQL database managed via PHPMyAdmin.

## Features

- **ETL Job Monitoring:** Real-time and historical visualization of ETL job execution.
- **Performance Analytics:** Track job duration, resource consumption, and data throughput.
- **Error and Anomaly Detection:** Identify and highlight job failures or unusual behavior.
- **Dashboarding:** Interactive dashboards for a comprehensive overview of ETL operations.

## Technologies Used

- **Symfony Framework:** A robust PHP framework for building web applications.
- **PHP:** The server-side scripting language.
- **MySQL Database:** Relational database for storing ETL job metadata and performance metrics.
- **PHPMyAdmin:** Web-based tool for managing MySQL databases.
- **(Add your specific frontend technologies here, e.g., JavaScript, Chart.js, D3.js, etc.)**

## Installation

Follow these steps to get your development environment up and running.

### 1. Clone the Repository

```bash
git clone https://github.com/Malekchairat/Job-visualisation-platform.git
cd Job-visualisation-platform
```

### 2. Install PHP Dependencies

Use Composer to install the project's PHP dependencies:

```bash
composer install
```

### 3. Database Setup (MySQL with PHPMyAdmin)

This project uses a MySQL database. You will need to set up a database and import the provided SQL schema.

#### a. Create a MySQL Database

Using PHPMyAdmin or your preferred MySQL client, create a new database for this project. For example, you can name it `etl_job_db`.

#### b. Configure Database Connection

Update your `.env` file (or `.env.local` for local development) with your database credentials. Look for the `DATABASE_URL` variable and modify it as follows:

```dotenv
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/etl_job_db?serverVersion=5.7"
```

Replace `db_user`, `db_password`, and `etl_job_db` with your actual database username, password, and the name of the database you created.

#### c. Import Database Schema

An SQL file containing the necessary database tables is provided in this repository. You will import this file into your newly created database.

**Using PHPMyAdmin:**
1. Open PHPMyAdmin in your web browser.
2. Select the database you created for this project (e.g., `etl_job_db`) from the left sidebar.
3. Click on the `Import` tab in the top menu.
4. Click the `Choose File` button and select the `database.sql` file from your cloned repository (e.g., `Job-visualisation-platform/sql/database.sql`).
5. Ensure the format is SQL and click `Go` at the bottom of the page to start the import.

**Using MySQL Command Line (Alternative):**
If you prefer using the command line, navigate to your project's root directory in your terminal and run:

```bash
mysql -u your_db_user -p etl_job_db < sql/database.sql
```

Replace `your_db_user` with your MySQL username and `etl_job_db` with your database name. You will be prompted for your database password.

### 4. Run Symfony Migrations (Optional, if using Doctrine Migrations)

If your project uses Doctrine Migrations, you might need to run them after importing the SQL file to ensure the database schema is up-to-date with your Symfony entities:

```bash
php bin/console doctrine:migrations:migrate
```

### 5. Start the Symfony Development Server

```bash
symfony server:start
```

Your application should now be accessible at `http://127.0.0.1:8000` (or the address provided by the Symfony server).




## Contact

For any questions or issues, please open an issue on GitHub or contact malek.chairat@esprit.tn or malekchairat2@gmail.com.

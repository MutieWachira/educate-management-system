Create database ems_db

//Users table
create table users(
    userID int(10) PRIMARY KEY AUTO_INCREMENT,
    full_name varchar(50) NOT NULL,
    email varchar(255) NOT NULL,
    password varchar(50) NOT NULL,
    role ENUM('ADMIN', 'LECTURER', 'STUDENT') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `users` (`userID`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES 
(NULL, 'admin', 'admin@ems.com', '123456', 'ADMIN', current_timestamp()), 
(NULL, 'student', 'student@ems.com', '123456', 'STUDENT', current_timestamp());
(NULL, 'lecturer', 'lecturer@ems.com', '123456', 'LECTURER', current_timestamp());

CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
);

CREATE TABLE courses (
  course_id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(30) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  department_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_department
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE TABLE lecturer_courses (
  lecturer_course_id INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id INT NOT NULL,
  course_id INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_lecturer_course (lecturer_id, course_id),
  CONSTRAINT fk_lc_lecturer
    FOREIGN KEY (lecturer_id) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_lc_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE enrollments (
  enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_student_course (student_id, course_id),
  CONSTRAINT fk_enroll_student
    FOREIGN KEY (student_id) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enroll_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE materials (
  material_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  uploaded_by INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_material_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_material_uploader
    FOREIGN KEY (uploaded_by) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

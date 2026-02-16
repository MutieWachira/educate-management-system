Create database ems_db

--Users table
create table users(
    userID int(10) PRIMARY KEY AUTO_INCREMENT,
    full_name varchar(50) NOT NULL,
    admission_no VARCHAR(30) NULL,
    staff_no VARCHAR(20) NULL,
    email varchar(255) NOT NULL,
    password varchar(255) NOT NULL,
    role ENUM('ADMIN', 'LECTURER', 'STUDENT') NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uniq_users_admission_no ON users(admission_no);
CREATE UNIQUE INDEX uq_users_staff_no ON users(staff_no);

INSERT INTO `users` (`userID`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES 
(NULL, 'admin', 'admin@ems.com', '123456', 'ADMIN', current_timestamp()), 
(NULL, 'student', 'student@ems.com', '123456', 'STUDENT', current_timestamp());
(NULL, 'lecturer', 'lecturer@ems.com', '123456', 'LECTURER', current_timestamp());

--department table
CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
);

--course table
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

--lecturer courses
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

--enrollments table
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

--uploded materials
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

--announcements table
CREATE TABLE announcements (
  announcement_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  posted_by INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  event_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ann_user
    FOREIGN KEY (posted_by) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

--forum threads
CREATE TABLE forum_threads (
  thread_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  created_by INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_thread_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_thread_user
    FOREIGN KEY (created_by) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

--forum replies
CREATE TABLE forum_replies (
  reply_id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  replied_by INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reply_thread
    FOREIGN KEY (thread_id) REFERENCES forum_threads(thread_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reply_user
    FOREIGN KEY (replied_by) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

--study group
CREATE TABLE study_groups (
  group_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  created_by INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_group_course
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_group_creator
    FOREIGN KEY (created_by) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

--study group members
CREATE TABLE study_group_members (
  member_id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  student_id INT NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_group_student (group_id, student_id),
  CONSTRAINT fk_member_group
    FOREIGN KEY (group_id) REFERENCES study_groups(group_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_member_student
    FOREIGN KEY (student_id) REFERENCES users(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(40) NOT NULL,        -- ANNOUNCEMENT, FORUM_REPLY, GROUP_JOIN, etc
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(userID)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);

CREATE TABLE activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(user_id),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id)
    REFERENCES users(userID) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE grades (
  grade_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  student_id INT NOT NULL,
  lecturer_id INT NULL,
  item_name VARCHAR(120) NOT NULL,   -- e.g. CAT 1, Assignment 2, Exam
  score DECIMAL(5,2) NOT NULL,
  max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
  out_of DECIMAL(5,2) NOT NULL DEFAULT 100,
  remarks VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_grade_course FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES users(userID) ON DELETE CASCADE
);
CREATE INDEX idx_grades_student_course ON grades(student_id, course_id);

-- 1) Assignments table
CREATE TABLE assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  created_by INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  due_date DATE NULL,
  max_score INT NOT NULL DEFAULT 100,
  grades_published TINYINT(1) NOT NULL DEFAULT 0,
  category_id INT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  file_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_assign_course FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_lecturer FOREIGN KEY (created_by) REFERENCES users(userID) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_category
  FOREIGN KEY (category_id) REFERENCES grade_categories(category_id)
  ON DELETE SET NULL
  ON UPDATE CASCADE
);

-- 2) Submissions table
CREATE TABLE submissions (
  submission_id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id INT NOT NULL,
  file_path VARCHAR(255) NULL,
  text_answer TEXT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  score DECIMAL(8,2) NULL,
  feedback TEXT NULL,
  graded_at TIMESTAMP NULL,
  graded_by INT NULL,

  UNIQUE KEY uq_one_submission (assignment_id, student_id),

  CONSTRAINT fk_sub_assign FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_student FOREIGN KEY (student_id) REFERENCES users(userID) ON DELETE CASCADE,
  CONSTRAINT fk_sub_grader FOREIGN KEY (graded_by) REFERENCES users(userID) ON DELETE SET NULL
);

CREATE TABLE grade_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  name VARCHAR(50) NOT NULL,          -- e.g. CAT, QUIZ, ASSIGNMENT
  weight DECIMAL(5,2) NOT NULL,       -- e.g. 30.00
  UNIQUE(course_id, name),
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

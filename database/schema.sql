CREATE DATABASE IF NOT EXISTS gcbskp_timetable;
USE gcbskp_timetable;

CREATE TABLE `academic_sessions` (
  `SessionID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(100) NOT NULL,
  `StartDate` DATE NULL,
  `EndDate` DATE NULL,
  `IsActive` BOOLEAN DEFAULT 0
);

CREATE TABLE `designation_workload` (
  `Designation` VARCHAR(50) PRIMARY KEY,
  `MaxWeeklyPeriods` INT NOT NULL,
  `HODWeeklyPeriods` INT NOT NULL
);

INSERT INTO `designation_workload` (`Designation`, `MaxWeeklyPeriods`, `HODWeeklyPeriods`) VALUES
('Lecturer', 18, 12),
('Assistant Professor', 15, 9),
('Associate Professor', 12, 6),
('Professor', 9, 6);

CREATE TABLE `notices` (
  `NoticeID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `Content` TEXT,
  `ImagePath` VARCHAR(255) NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `IsActive` BOOLEAN DEFAULT 1
);

CREATE TABLE `downloads` (
  `DocumentID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `FilePath` VARCHAR(255) NOT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `IsActive` BOOLEAN DEFAULT 1
);

CREATE TABLE `programs` (
  `ProgramID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `PeriodUnit` ENUM('year', 'semester') NOT NULL
);

INSERT INTO `programs` (`Name`, `PeriodUnit`) VALUES
('Intermediate', 'year'),
('ADP', 'year'),
('BS-4YDP', 'semester'),
('BS-2YDP', 'semester'),
('BED', 'semester'),
('BS Four Years', 'semester');

CREATE TABLE `departments` (
  `DepartmentID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `Name` VARCHAR(100) NOT NULL,
  `Type` ENUM('department', 'group') NOT NULL,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE
);

CREATE TABLE `semesters` (
  `SemesterID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `Label` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE
);

CREATE TABLE `shifts` (
  `ShiftID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` ENUM('Morning', 'Evening') NOT NULL
);

INSERT INTO `shifts` (`Name`) VALUES ('Morning'), ('Evening');

CREATE TABLE `time_slots` (
  `SlotID` INT AUTO_INCREMENT PRIMARY KEY,
  `ShiftID` INT NOT NULL,
  `PeriodNumber` INT NOT NULL,
  `StartTime` TIME NOT NULL,
  `EndTime` TIME NOT NULL,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE
);

INSERT INTO `time_slots` (`ShiftID`, `PeriodNumber`, `StartTime`, `EndTime`) VALUES
(1, 1, '08:30:00', '09:10:00'),(1, 2, '09:10:00', '09:50:00'),(1, 3, '09:50:00', '10:30:00'),(1, 4, '10:30:00', '11:10:00'),(1, 5, '11:10:00', '11:50:00'),(1, 6, '11:50:00', '12:30:00'),
(2, 1, '13:30:00', '14:10:00'),(2, 2, '14:10:00', '14:50:00'),(2, 3, '14:50:00', '15:30:00'),(2, 4, '15:30:00', '16:10:00'),(2, 5, '16:10:00', '16:50:00'),(2, 6, '16:50:00', '17:30:00');

CREATE TABLE `users` (
  `UserID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(100) UNIQUE NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Role` ENUM('admin', 'teacher') NOT NULL,
  `Designation` VARCHAR(50) NULL,
  `IsHOD` BOOLEAN DEFAULT FALSE,
  `HOD_DepartmentID` INT NULL,
  `FatherName` VARCHAR(100) NULL,
  `Gender` ENUM('Male', 'Female', 'Other') NULL,
  `DateOfBirth` DATE NULL,
  `CNIC` VARCHAR(50) NULL,
  `ProfilePicture` VARCHAR(255) NULL,
  `Phone` VARCHAR(20) NULL,
  `Address` TEXT NULL,
  `DepartmentID` INT NULL,
  `Qualification` VARCHAR(100) NULL,
  `Specialization` VARCHAR(100) NULL,
  `JoiningDate` DATE NULL,
  `EmploymentType` ENUM('Permanent', 'Contract', 'Visiting') NULL,
  `Experience` VARCHAR(50) NULL,
  `AccountStatus` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `EmployeeID` VARCHAR(50) NULL UNIQUE,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`Designation`) REFERENCES `designation_workload`(`Designation`) ON DELETE SET NULL,
  FOREIGN KEY (`HOD_DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE SET NULL,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE SET NULL
);

-- Default Admin
INSERT INTO `users` (`Name`, `Email`, `Password`, `Role`) VALUES 
('Admin', 'allfouryou987@gmail.com', '$2y$10$9E0bC4PwjrY.4l6kPPMAeev2udXqVzPmcGUCL3EHAv8oyNPYDx.Ze', 'admin'); -- password: asdf1234

CREATE TABLE `rooms` (
  `RoomID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `Type` ENUM('Classroom', 'Lab') NOT NULL
);

CREATE TABLE `sections` (
  `SectionID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `DepartmentID` INT NOT NULL,
  `SemesterID` INT NOT NULL,
  `Name` VARCHAR(50) NOT NULL,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE
);

CREATE TABLE `courses` (
  `CourseID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `ProgramID` INT NOT NULL,
  `DepartmentID` INT NOT NULL,
  `SemesterID` INT NOT NULL,
  `ShiftID` INT NOT NULL,
  `SectionID` INT NULL,
  `TeacherID` INT NOT NULL,
  `CreditHours` INT NOT NULL,
  `RoomType` ENUM('Classroom', 'Lab') NOT NULL,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE,
  FOREIGN KEY (`SectionID`) REFERENCES `sections`(`SectionID`) ON DELETE CASCADE,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE `timetable` (
  `TimetableID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `DepartmentID` INT NOT NULL,
  `SemesterID` INT NOT NULL,
  `ShiftID` INT NOT NULL,
  `SessionID` INT NULL,
  `SectionID` INT NULL,
  `Day` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  `SlotID` INT NOT NULL,
  `CourseID` INT NULL,
  `TeacherID` INT NULL,
  `RoomID` INT NULL,
  `IsFree` BOOLEAN DEFAULT 0,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE,
  FOREIGN KEY (`SessionID`) REFERENCES `academic_sessions`(`SessionID`) ON DELETE SET NULL,
  FOREIGN KEY (`SectionID`) REFERENCES `sections`(`SectionID`) ON DELETE CASCADE,
  FOREIGN KEY (`SlotID`) REFERENCES `time_slots`(`SlotID`) ON DELETE CASCADE,
  FOREIGN KEY (`CourseID`) REFERENCES `courses`(`CourseID`) ON DELETE SET NULL,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE SET NULL,
  FOREIGN KEY (`RoomID`) REFERENCES `rooms`(`RoomID`) ON DELETE SET NULL
);

CREATE TABLE `requests` (
  `RequestID` INT AUTO_INCREMENT PRIMARY KEY,
  `Type` ENUM('free_change', 'swap') NOT NULL,
  `RequestedBy` INT NOT NULL,
  `TargetTeacherID` INT NULL,
  `TimetableID` INT NOT NULL,
  `NewSlotID` INT NULL,
  `SwapTimetableID` INT NULL,
  `Status` ENUM('pending_teacher', 'pending_admin', 'approved', 'rejected', 'cancelled') NOT NULL,
  `TeacherBStatus` ENUM('pending', 'accepted', 'declined') NULL,
  `Deadline` DATETIME NULL,
  `AdminNote` TEXT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`RequestedBy`) REFERENCES `users`(`UserID`) ON DELETE CASCADE,
  FOREIGN KEY (`TargetTeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE,
  FOREIGN KEY (`TimetableID`) REFERENCES `timetable`(`TimetableID`) ON DELETE CASCADE,
  FOREIGN KEY (`NewSlotID`) REFERENCES `time_slots`(`SlotID`) ON DELETE CASCADE,
  FOREIGN KEY (`SwapTimetableID`) REFERENCES `timetable`(`TimetableID`) ON DELETE CASCADE
);

CREATE TABLE `leave_requests` (
  `LeaveID` INT AUTO_INCREMENT PRIMARY KEY,
  `TeacherID` INT NOT NULL,
  `FromDate` DATE NOT NULL,
  `ToDate` DATE NOT NULL,
  `Shift` ENUM('Morning', 'Evening', 'Both') NOT NULL,
  `Reason` TEXT NOT NULL,
  `Status` ENUM('pending', 'approved', 'rejected') NOT NULL,
  `AdminNote` TEXT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE `substitute_assignments` (
  `SubstituteID` INT AUTO_INCREMENT PRIMARY KEY,
  `TimetableID` INT NOT NULL,
  `OriginalTeacherID` INT NOT NULL,
  `SubstituteTeacherID` INT NOT NULL,
  `FromDate` DATE NOT NULL,
  `ToDate` DATE NOT NULL,
  `Status` ENUM('active', 'expired') NOT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`TimetableID`) REFERENCES `timetable`(`TimetableID`) ON DELETE CASCADE,
  FOREIGN KEY (`OriginalTeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE,
  FOREIGN KEY (`SubstituteTeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE `notifications` (
  `NotificationID` INT AUTO_INCREMENT PRIMARY KEY,
  `ScopeType` ENUM('global', 'program', 'class', 'teacher') NOT NULL,
  `ProgramID` INT NULL,
  `DepartmentID` INT NULL,
  `SemesterID` INT NULL,
  `ShiftID` INT NULL,
  `TeacherID` INT NULL,
  `Message` TEXT NOT NULL,
  `IsRead` BOOLEAN DEFAULT FALSE,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE `college_calendar` (
  `CalendarID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `Date` DATE NOT NULL,
  `EventTime` VARCHAR(50) DEFAULT 'All Day',
  `Category` ENUM('holiday', 'academic', 'examination', 'event', 'meeting', 'other') NOT NULL DEFAULT 'event',
  `ImagePath` VARCHAR(255) NULL,
  `Description` TEXT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

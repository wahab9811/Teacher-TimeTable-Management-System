CREATE DATABASE IF NOT EXISTS gcbskp_timetable;
USE gcbskp_timetable;

CREATE TABLE IF NOT EXISTS `academic_sessions` (
  `SessionID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(100) NOT NULL,
  `StartDate` DATE NULL,
  `EndDate` DATE NULL,
  `IsActive` BOOLEAN DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `designation_workload` (
  `Designation` VARCHAR(50) PRIMARY KEY,
  `MaxWeeklyPeriods` INT NOT NULL,
  `HODWeeklyPeriods` INT NOT NULL
);

INSERT IGNORE INTO `designation_workload` (`Designation`, `MaxWeeklyPeriods`, `HODWeeklyPeriods`) VALUES
('Lecturer', 18, 12),
('Assistant Professor', 15, 9),
('Associate Professor', 12, 6),
('Professor', 9, 6);

CREATE TABLE IF NOT EXISTS `notices` (
  `NoticeID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `Content` TEXT,
  `ImagePath` VARCHAR(255) NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `IsActive` BOOLEAN DEFAULT 1
);

CREATE TABLE IF NOT EXISTS `downloads` (
  `DocumentID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `FilePath` VARCHAR(255) NOT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `IsActive` BOOLEAN DEFAULT 1
);

CREATE TABLE IF NOT EXISTS `programs` (
  `ProgramID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `ShortCode` VARCHAR(20) NULL,
  `PeriodUnit` ENUM('year', 'semester') NOT NULL,
  `TotalDuration` INT NOT NULL DEFAULT 0,
  `AllowedShifts` ENUM('Morning', 'Evening', 'Both') NOT NULL DEFAULT 'Both',
  `IsActive` BOOLEAN NOT NULL DEFAULT 1
);

INSERT IGNORE INTO `programs` (`Name`, `ShortCode`, `PeriodUnit`, `TotalDuration`) VALUES
('Intermediate', 'ICS/FA', 'year', 2),
('ADP', 'ADP', 'year', 2),
('BS-4YDP', 'BS', 'semester', 8),
('BS-2YDP', 'BS5th', 'semester', 4),
('BED', 'B.Ed', 'semester', 3),
('BS Four Years', 'BS4', 'semester', 8);

CREATE TABLE IF NOT EXISTS `departments` (
  `DepartmentID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `Name` VARCHAR(100) NOT NULL,
  `ShortCode` VARCHAR(20) NULL,
  `Type` ENUM('department', 'group') NOT NULL,
  `IsActive` BOOLEAN NOT NULL DEFAULT 1,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `semesters` (
  `SemesterID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `Label` VARCHAR(50) NOT NULL,
  `IsActive` BOOLEAN NOT NULL DEFAULT 1,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `shifts` (
  `ShiftID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` ENUM('Morning', 'Evening') NOT NULL
);

INSERT IGNORE INTO `shifts` (`Name`) VALUES ('Morning'), ('Evening');

CREATE TABLE IF NOT EXISTS `time_slots` (
  `SlotID` INT AUTO_INCREMENT PRIMARY KEY,
  `ShiftID` INT NOT NULL,
  `PeriodNumber` INT NOT NULL,
  `StartTime` TIME NOT NULL,
  `EndTime` TIME NOT NULL,
  `FridayStartTime` TIME NULL,
  `FridayEndTime` TIME NULL,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE
);

INSERT IGNORE INTO `time_slots` (`ShiftID`, `PeriodNumber`, `StartTime`, `EndTime`, `FridayStartTime`, `FridayEndTime`) VALUES
(1, 1, '08:30:00', '09:15:00', '08:30:00', '09:00:00'),
(1, 2, '09:15:00', '10:00:00', '09:00:00', '09:30:00'),
(1, 3, '10:00:00', '10:45:00', '09:30:00', '10:00:00'),
(1, 4, '10:45:00', '11:30:00', '10:00:00', '10:30:00'),
(1, 5, '11:30:00', '12:15:00', '10:30:00', '11:00:00'),
(1, 6, '12:15:00', '13:00:00', '11:00:00', '11:30:00'),
(2, 1, '13:30:00', '14:15:00', '13:30:00', '14:00:00'),
(2, 2, '14:15:00', '15:00:00', '14:00:00', '14:30:00'),
(2, 3, '15:00:00', '15:45:00', '14:30:00', '15:00:00'),
(2, 4, '15:45:00', '16:30:00', '15:00:00', '15:30:00'),
(2, 5, '16:30:00', '17:15:00', '15:30:00', '16:00:00'),
(2, 6, '17:15:00', '18:00:00', '16:00:00', '16:30:00');

CREATE TABLE IF NOT EXISTS `users` (
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
INSERT IGNORE INTO `users` (`Name`, `Email`, `Password`, `Role`) VALUES 
('Admin', 'allfouryou987@gmail.com', '$2y$10$9E0bC4PwjrY.4l6kPPMAeev2udXqVzPmcGUCL3EHAv8oyNPYDx.Ze', 'admin'); -- password: asdf1234

CREATE TABLE IF NOT EXISTS `rooms` (
  `RoomID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `Type` ENUM('Classroom', 'Lab') NOT NULL,
  `AssignFor` VARCHAR(255) DEFAULT NULL,
  `IsActive` BOOLEAN NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS `sections` (
  `SectionID` INT AUTO_INCREMENT PRIMARY KEY,
  `ProgramID` INT NOT NULL,
  `DepartmentID` INT NOT NULL,
  `SemesterID` INT NOT NULL,
  `ShiftID` INT NOT NULL DEFAULT 1,
  `Name` VARCHAR(50) NOT NULL,
  `InchargeID` INT NULL,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE,
  FOREIGN KEY (`InchargeID`) REFERENCES `users`(`UserID`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `courses` (
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

CREATE TABLE IF NOT EXISTS `timetable` (
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

CREATE TABLE IF NOT EXISTS `requests` (
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

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `LeaveID` INT AUTO_INCREMENT PRIMARY KEY,
  `TeacherID` INT NOT NULL,
  `LeaveType` ENUM('Casual Leave', 'Medical Leave', 'Official Duty', 'Short Leave', 'Other') NOT NULL DEFAULT 'Casual Leave',
  `FromDate` DATE NOT NULL,
  `ToDate` DATE NOT NULL,
  `Shift` ENUM('Morning', 'Evening', 'Both') NOT NULL,
  `Reason` TEXT NOT NULL,
  `Status` ENUM('pending', 'approved', 'rejected') NOT NULL,
  `AdminNote` TEXT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `substitute_assignments` (
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

CREATE TABLE IF NOT EXISTS `notifications` (
  `NotificationID` INT AUTO_INCREMENT PRIMARY KEY,
  `ScopeType` ENUM('global', 'program', 'class', 'teacher', 'department', 'all_teachers', 'all_hods') NOT NULL,
  `ProgramID` INT NULL,
  `DepartmentID` INT NULL,
  `SemesterID` INT NULL,
  `ShiftID` INT NULL,
  `TeacherID` INT NULL,
  `Message` TEXT NOT NULL,
  `IsRead` BOOLEAN DEFAULT FALSE,
  `ExpiryDate` DATETIME NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ProgramID`) REFERENCES `programs`(`ProgramID`) ON DELETE CASCADE,
  FOREIGN KEY (`DepartmentID`) REFERENCES `departments`(`DepartmentID`) ON DELETE CASCADE,
  FOREIGN KEY (`SemesterID`) REFERENCES `semesters`(`SemesterID`) ON DELETE CASCADE,
  FOREIGN KEY (`ShiftID`) REFERENCES `shifts`(`ShiftID`) ON DELETE CASCADE,
  FOREIGN KEY (`TeacherID`) REFERENCES `users`(`UserID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `college_calendar` (
  `CalendarID` INT AUTO_INCREMENT PRIMARY KEY,
  `Title` VARCHAR(255) NOT NULL,
  `Date` DATE NOT NULL,
  `EventTime` VARCHAR(50) DEFAULT 'All Day',
  `Category` ENUM('holiday', 'academic', 'examination', 'event', 'meeting', 'other') NOT NULL DEFAULT 'event',
  `ImagePath` VARCHAR(255) NULL,
  `Description` TEXT NULL,
  `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reports Table
CREATE TABLE IF NOT EXISTS student_reports (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    StudentName VARCHAR(100) NOT NULL,
    ProgramDept VARCHAR(100),
    IssueType VARCHAR(50),
    Message TEXT NOT NULL,
    Status ENUM('pending', 'resolved') DEFAULT 'pending',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

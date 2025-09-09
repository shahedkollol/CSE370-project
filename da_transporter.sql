-- Create Database
CREATE DATABASE da_transporter;
USE da_transporter;

-- USER MANAGEMENT TABLES
-- Main User Table
CREATE TABLE User (
    NID VARCHAR(20) PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Phone VARCHAR(15) UNIQUE NOT NULL,
    Emergency_Contact VARCHAR(15),
    Area VARCHAR(100),
    PS VARCHAR(100), -- Police Station
    Gender ENUM('Male', 'Female', 'Other'),
    Email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL;
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (Phone),
    INDEX idx_email (Email),
    INDEX idx_area (Area)
);

-- Admin Table
CREATE TABLE Admin (
    Gsuite VARCHAR(100) PRIMARY KEY,
    Password VARCHAR(255) NOT NULL,
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rider User Table (Students/Regular Riders)
CREATE TABLE Rider_User (
    NID VARCHAR(20) PRIMARY KEY,
    Std_ID VARCHAR(20) UNIQUE, -- Student ID if applicable
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE
);

-- Rider Taker Table (Drivers/Service Providers)
CREATE TABLE Rider_Taker (
    NID VARCHAR(20) PRIMARY KEY,
    Rider_ID VARCHAR(20) UNIQUE NOT NULL,
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE,
    INDEX idx_rider_id (Rider_ID)
);

-- VEHICLE MANAGEMENT TABLES
-- Private Car Table
CREATE TABLE Private_Car (
    Vehicle_Num VARCHAR(20) PRIMARY KEY,
    Car_Model VARCHAR(50),
    Owner_ID VARCHAR(20) NOT NULL,
    Capacity INT NOT NULL CHECK (Capacity > 0),
    License_Num VARCHAR(30) UNIQUE NOT NULL,
    Car_Type ENUM('Sedan', 'SUV', 'Hatchback', 'Microbus', 'Other'),
    NID VARCHAR(20),
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE,
    INDEX idx_owner_id (Owner_ID),
    INDEX idx_car_type (Car_Type)
);

-- Bus Table
CREATE TABLE Bus (
    Bus_ID VARCHAR(20) PRIMARY KEY,
    Schedule TEXT, -- JSON or structured schedule data
    Capacity INT NOT NULL DEFAULT 40,
    Bus_Type ENUM('AC', 'Non-AC', 'Deluxe', 'Standard') DEFAULT 'Standard',
    License_Num VARCHAR(30) UNIQUE,
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ROUTE MANAGEMENT TABLES
-- Route Table
CREATE TABLE Route (
    Route_ID INT AUTO_INCREMENT PRIMARY KEY,
    Start VARCHAR(100) NOT NULL,
    End VARCHAR(100) NOT NULL,
    Stop1 VARCHAR(100),
    Stop2 VARCHAR(100),
    Stop3 VARCHAR(100),
    Distance DECIMAL(10,2), -- in KM
    Estimated_Duration INT, -- in minutes
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_start_end (Start, End)
);

-- TRIP MANAGEMENT TABLES
-- Trip Table
CREATE TABLE Bus_Booking (
    Booking_ID INT AUTO_INCREMENT PRIMARY KEY,
    Book_Code VARCHAR(20) UNIQUE NOT NULL,
    Bus_ID VARCHAR(20) NOT NULL,
    NID VARCHAR(20) NOT NULL,
    Book_Slot INT NOT NULL DEFAULT 1,
    Booking_Status ENUM('Booked', 'Confirmed', 'Cancelled') DEFAULT 'Booked',
    Payment_Status ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    Booked_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (Bus_ID) REFERENCES Bus(Bus_ID) ON DELETE CASCADE,
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE,
    
    INDEX idx_book_code (Book_Code),
    INDEX idx_user_bookings (NID),
    INDEX idx_bus_bookings (Bus_ID),
    INDEX idx_booking_status (Booking_Status)
);
    
    FOREIGN KEY (Creator_ID) REFERENCES User(NID) ON DELETE CASCADE,
    FOREIGN KEY (Route_ID) REFERENCES Route(Route_ID),
    FOREIGN KEY (Vehicle_Num) REFERENCES Private_Car(Vehicle_Num),
    FOREIGN KEY (Bus_ID) REFERENCES Bus(Bus_ID),
    
    INDEX idx_creator (Creator_ID),
    INDEX idx_date_time (Date, Time),
    INDEX idx_status (Trip_Status),
    INDEX idx_req_type (Req_Type),
    INDEX idx_start_dest (Start_Point, Destination)
);

-- Trip Join Table (Many-to-Many relationship between Users and Trips)
CREATE TABLE Trip_Join (
    Join_ID INT AUTO_INCREMENT PRIMARY KEY,
    NID VARCHAR(20) NOT NULL,
    Trip_ID INT NOT NULL,
    Joined_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Requested', 'Accepted', 'Rejected', 'Completed') DEFAULT 'Requested',
    
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE,
    FOREIGN KEY (Trip_ID) REFERENCES Trip(Trip_ID) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_trip (NID, Trip_ID),
    INDEX idx_user_joins (NID),
    INDEX idx_trip_joins (Trip_ID)
);

-- Bus Booking Table (Specific for bus reservations)
CREATE TABLE Bus_Booking (
    Book_Code VARCHAR(20) PRIMARY KEY,
    Trip_ID INT NOT NULL,
    NID VARCHAR(20) NOT NULL,
    Book_Slot INT NOT NULL, -- Seat number
    Booking_Status ENUM('Booked', 'Confirmed', 'Cancelled') DEFAULT 'Booked',
    Payment_Status ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    Booked_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (Trip_ID) REFERENCES Trip(Trip_ID) ON DELETE CASCADE,
    FOREIGN KEY (NID) REFERENCES User(NID) ON DELETE CASCADE,
    
    UNIQUE KEY unique_trip_slot (Trip_ID, Book_Slot),
    INDEX idx_user_bookings (NID),
    INDEX idx_trip_bookings (Trip_ID)
);

-- REVIEW SYSTEM TABLES

-- Review Table
CREATE TABLE Review (
    Review_ID INT AUTO_INCREMENT PRIMARY KEY,
    Rater_ID VARCHAR(20) NOT NULL, -- User giving the review
    Rated_ID VARCHAR(20) NOT NULL, -- User being reviewed
    Trip_ID INT NOT NULL, -- Associated trip
    Rating INT NOT NULL CHECK (Rating >= 1 AND Rating <= 5),
    Comment TEXT,
    Created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (Rater_ID) REFERENCES User(NID) ON DELETE CASCADE,
    FOREIGN KEY (Rated_ID) REFERENCES User(NID) ON DELETE CASCADE,
    FOREIGN KEY (Trip_ID) REFERENCES Trip(Trip_ID) ON DELETE CASCADE,
    
    UNIQUE KEY unique_review (Rater_ID, Rated_ID, Trip_ID),
    INDEX idx_rated_user (Rated_ID),
    INDEX idx_rating (Rating),
    INDEX idx_trip_reviews (Trip_ID)
);

-- Notifications Table
CREATE TABLE Notifications (
    Notification_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID VARCHAR(20),
    Message TEXT,
    Status ENUM('Unread', 'Read') DEFAULT 'Unread',
    Created_At DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES User(NID)
);

-- View for Active Trips
CREATE VIEW Active_Trips AS
SELECT 
    t.Trip_ID,
    t.Creator_ID,
    u.Name as Creator_Name,
    t.Req_Type,
    t.Trip_Status,
    t.Start_Point,
    t.Destination,
    t.Fare,
    t.Capacity_Used,
    t.Date,
    t.Time,
    COALESCE(pc.Capacity, b.Capacity, 0) as Total_Capacity
FROM Trip t
LEFT JOIN User u ON t.Creator_ID = u.NID
LEFT JOIN Private_Car pc ON t.Vehicle_Num = pc.Vehicle_Num
LEFT JOIN Bus b ON t.Bus_ID = b.Bus_ID
WHERE t.Trip_Status IN ('Pending', 'Confirmed', 'In_Progress');

-- View for User Trip History
CREATE VIEW User_Trip_History AS
SELECT 
    u.NID,
    u.Name,
    t.Trip_ID,
    t.Start_Point,
    t.Destination,
    t.Fare,
    t.Date,
    t.Time,
    t.Trip_Status,
    tj.Status as Join_Status,
    'Passenger' as Role
FROM User u
JOIN Trip_Join tj ON u.NID = tj.NID
JOIN Trip t ON tj.Trip_ID = t.Trip_ID

UNION ALL

SELECT 
    u.NID,
    u.Name,
    t.Trip_ID,
    t.Start_Point,
    t.Destination,
    t.Fare,
    t.Date,
    t.Time,
    t.Trip_Status,
    'Creator' as Join_Status,
    'Driver/Creator' as Role
FROM User u
JOIN Trip t ON u.NID = t.Creator_ID;

-- Trigger
DELIMITER //
CREATE TRIGGER update_capacity_after_join
    AFTER INSERT ON Trip_Join
    FOR EACH ROW
BEGIN
    IF NEW.Status = 'Accepted' THEN
        UPDATE Trip 
        SET Capacity_Used = Capacity_Used + 1 
        WHERE Trip_ID = NEW.Trip_ID;
    END IF;
END//

-- Trigger to update capacity
CREATE TRIGGER update_capacity_after_leave
    AFTER UPDATE ON Trip_Join
    FOR EACH ROW
BEGIN
    IF OLD.Status = 'Accepted' AND NEW.Status != 'Accepted' THEN
        UPDATE Trip 
        SET Capacity_Used = Capacity_Used - 1 
        WHERE Trip_ID = NEW.Trip_ID;
    ELSEIF OLD.Status != 'Accepted' AND NEW.Status = 'Accepted' THEN
        UPDATE Trip 
        SET Capacity_Used = Capacity_Used + 1 
        WHERE Trip_ID = NEW.Trip_ID;
    END IF;
END//

DELIMITER ;

-- Show table structure summary
SELECT 
    TABLE_NAME,
    TABLE_COMMENT,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = t.TABLE_NAME AND TABLE_SCHEMA = 'da_transporter') as Column_Count
FROM INFORMATION_SCHEMA.TABLES t
WHERE TABLE_SCHEMA = 'da_transporter' 
AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
# Overview

DanceOffice is a comprehensive dance sport competition management system that integrates with DanceScore to provide seamless tournament organization and result presentation. The system serves as a unified platform for managing competitions, displaying real-time results, and providing intuitive navigation for participants and spectators.

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Frontend Architecture
The system uses a responsive web design with distinct layouts for desktop and mobile:
- **Desktop Layout**: Three-column structure with left navigation, main content area, and right sidebar for advertisements
- **Mobile Layout**: Header navigation, main content, and bottom navigation bar
- **CSS Framework**: Custom CSS using Grid and Flexbox for responsive design
- **Typography**: Google Fonts (Noto Sans KR) for Korean language support
- **Icons**: Material Symbols for consistent iconography

## Backend Architecture
- **Server Technology**: PHP 7.4+ with file-based data storage
- **Data Storage**: JSON files for competition data and text files for configuration
- **Session Management**: PHP sessions for user state management
- **File Processing**: Automated synchronization with DanceScore results

## Competition Management System
- **Scheduler System**: Unified scheduler that integrates both DanceScore and internal system results
- **Competition States**: Automatic state management (upcoming, ongoing, completed)
- **Result Integration**: Real-time monitoring and processing of DanceScore output files
- **Content Management**: JSON-based flexible competition information storage

## Real-time Result Monitoring
- **File Watcher**: Continuous monitoring of DanceScore result directories (C:\dancescore\Web)
- **Result Processing**: Automatic combination of summary and detailed result files
- **Live Display**: Full-screen monitoring system with 30-second auto-refresh
- **Event Control**: Real-time event status and result announcement control

## Data Processing Pipeline
- **Text Processing**: Regular expression-based content replacement for Korean localization
- **File Synchronization**: Automated copying and conversion from DanceScore to web directory
- **Content Filtering**: Removal of copyrighted content and branding elements
- **Link Management**: Dynamic URL rewriting for proper navigation

## User Interface Components
- **Card-based Design**: Modern card layout for competition listings
- **Timeline View**: Chronological display of competition history
- **Weekly Status**: Clear distinction between past, current, and upcoming week competitions
- **Responsive Tables**: Mobile-optimized result display tables

# External Dependencies

## DanceScore Integration
- **Primary Dependency**: DanceScore competition management software
- **File Locations**: C:\dancescore\Web for results, C:\dancescore\Recall for recall data
- **File Formats**: HTML files with numeric naming convention (e.g., 1.html, 1-d.html)
- **Synchronization**: Real-time file monitoring and processing

## Web Technologies
- **Google Fonts API**: For Noto Sans KR and Material Symbols
- **Frontend Libraries**: No external JavaScript frameworks (vanilla JS approach)
- **Image Processing**: PIL (Python Imaging Library) for logo generation

## File System Dependencies
- **Network Drive**: Y: drive mapping for shared file access
- **Directory Structure**: Specific folder organization for results, assets, and data
- **File Permissions**: Read/write access to competition directories

## Development Tools
- **Python Scripts**: For logo generation and file synchronization
- **Batch Scripts**: Windows automation for monitoring processes
- **Text Processing**: Regular expressions for content localization

## Server Requirements
- **Web Server**: Apache or IIS with PHP support
- **File System**: Windows-based file paths and drive mappings
- **Network Access**: Shared drive connectivity for DanceScore integration
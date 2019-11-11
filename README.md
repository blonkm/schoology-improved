# Schoology-improved
_**Using the Schoology API to do things you can't using the web version**_

Schoology is a powerful Electronic Learning Environment. It helps you to manage materials, students, courses, etc. Still, some functionality is lacking.

The Schoology SDK uses PHP to access Schoology objects. The Schoology Support site offers good documentation, but lacks example code. 

This project aims to solve these problems.

## Project Goals
### Grading groups
Notably missing from Schoology is the ability to import and export grading groups. The class methods for groups will allow you to create, view and export grading groups.

### Attachments
Another major functionality missing in Schoology is a way to download all submissions from one student. You can now search for a student and download their submitted attachments.

For any additional information please visit http://developers.schoology.com/

## About the code
The basic functionality is in a class called 'Course'. You will be able to retrieve information regarding the course with the methods in this class.

## How to use the project

### Step 1: prerequisites
You will need a server capable of running PHP. It will also need ZipArchive enabled, which is available as of PHP 5.2.0. You will also need to request an API code from Schoology support.

### Step 1: setup folders
- Download the files from this project (schoology sdk is included)
- Upload the files to a folder on your web server
- Make sure there is write access to this folder from within PHP (on linux: chmod 755 <foldername>)

### Configuration
You should start by copying all files to your server. Then, in the config directory create three files:

```
    .api_key
    .api_secret
    .timetable
```
In the first two files put your key and secret resepectively Just the string, nothing else. The .timetable file is for a possible school timetable, so you can view attendance according to schedule. The files may be hidden from view, so make sure to enable view hidden files (e.g. in FTP or SCP program).

Note: there is a method in helpers.php named 'formatId' which is specific for the school this was made for originally. You may need to change this function.

### Prepare import
The import of groups is done by setting up a PHP array in ``groups_import.php````. This is only temporary, the aim is to import directly from a csv file. Ideally a schoology formatted export file can be used.

### Run import
Now it's time to run the import by opening your browser, selecting a course, and then 'create'. There's also the 'import' option but it's not functional as of yet.

### Download submissions
You can see all groups and links to their submission pages by first selecting a course, and then select 'submissions'.

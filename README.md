#Schoology-improved
Using the Schoology API to do things you can't using the web version

Schoology is a powerful Electronic Learning Environment. It helps you to manage materials, students, courses, etc. Still, some functionality is lacking.

The Schoology SDK uses PHP to access Schoology objects. The Schoology Support site offers good documentation, but lacks example code. 

This project aims to solve these problems.

##How to use the classes

### Configuration
You should start by copying all files to your server. Then, in config.php, modify these lines: 

```
    const API_KEY = 'YOUR API KEY';
    const API_SECRET = 'YOUR API SECRET';
```

### Course
The basic functionality is in a class called 'Course'. You will be able to retrieve information regarding the course with the methods in this class.

### Grading groups
Notably missing from Schoology is the ability to import and export grading groups. The class methods for groups will allow you to create, view and export grading groups.

### Attachments
Another major functionality missing in Schoology is a way to download all submissions from one student. You can now search for a student and download their submitted attachments.



For any additional information please visit http://developers.schoology.com/

# Admin panel documentation

The Course Access package is designed to handle access management for courses.
It provides a set of functions that are used both in the Admin Panel and on the Front App.

## Course Access

In the course details, there is an "Access" tab.
In this section, you can both grant and revoke access to the course to individual users, as well as groups of users.

![Course Access Tab](https://github.com/EscolaLMS/Course-Access/assets/59456825/e4b8e2de-44e4-48de-93a3-aa64e77223de)

## Access Requests

To manage access requests submitted by users for the course, there is a separate tab called "Access Requests."
Within it, you will find user information, course details, and extra data.

![Course Access Requests](https://github.com/EscolaLMS/Course-Access/assets/59456825/f0a5d682-8832-4714-90a2-c9bbbcc2aa8e)

Clicking the "Approve" button grants access to the user for the course.
You can also remove the request.

![Approve or remove](https://github.com/EscolaLMS/Course-Access/assets/59456825/eff25fb2-5078-4823-b318-fa63908ce340)

In the Settings tab, you will find an option that allows all requests submitted by users to be automatically approved.

![Settings](https://github.com/EscolaLMS/Course-Access/assets/59456825/c3706bfb-3982-42c3-afb9-acddb64e32e2)

Automation can be used in the case of providing free access to a course, requiring only a request for course access. If we do not set the automatic function in the options, all course access requests must be approved by authorized individuals.

## Access to the course from the user's perspective.

The view of a access request from the Frontend of the platform looks as follows:

![image](https://github.com/EscolaLMS/Course-Access/assets/108077902/be3b4dfd-5bd3-4806-9479-15ad38ccb5ae)

Please note that the user must be logged into the Frontend of the platform for the access request button to be visible in the course selection panel.

The user can leave additional information that will allow the access-granting person to have additional contact or information justifying the request for course access.

![image](https://github.com/EscolaLMS/Course-Access/assets/108077902/a733c3a4-1c6c-494d-af32-14bd8331f6fa)

After filling in the contact information, the button changes to a message indicating that the access request has been sent. At this point, the person authorized to grant access can either accept or reject the access request.

![image](https://github.com/EscolaLMS/Course-Access/assets/108077902/613012a1-d067-4305-871d-2b88324b7c7c)


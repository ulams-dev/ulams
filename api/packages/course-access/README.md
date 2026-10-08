# Course Access

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Course-Access/)
[![codecov](https://codecov.io/gh/EscolaLMS/Course-Access/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Course-Access)
[![phpunit](https://github.com/EscolaLMS/Course-Access/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Course-Access/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/course-access)](https://packagist.org/packages/escolalms/course-access)
[![downloads](https://img.shields.io/packagist/v/escolalms/course-access)](https://packagist.org/packages/escolalms/course-access)
[![downloads](https://img.shields.io/packagist/l/escolalms/course-access)](https://packagist.org/packages/escolalms/course-access)
[![Maintainability](https://api.codeclimate.com/v1/badges/0c9e2593fb30e2048f95/maintainability)](https://codeclimate.com/github/EscolaLMS/Course-Access/maintainability)

## What does it do

This package is used to manage access to the course for individual users and user groups. 

Additionally, it introduces a mechanism where users can send access enquiries with additional information, and administrators (users with appropriate permissions) can approve or remove them.

## Installing

- `composer require escolalms/course-access`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\CourseAccess\Database\Seeders\CourseAccessPermissionSeeder"`

## Endpoints

The endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Course-Access/)

## Tests

Run `./vendor/bin/phpunit` to run tests.
Test details [![codecov](https://codecov.io/gh/EscolaLMS/Course-Access/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Course-Access)

## Events

### Course Access Enquiry
- `CourseAccessEnquiryAdminCreatedEvent` - This event is dispatched when a student creates an enquiry for course access.
- `CourseAccessEnquiryStudentCreatedEvent` - This event is dispatched to all users who have the `EnquiryStatusEnum::APPROVE_COURSE_ACCESS_ENQUIRY` permission, allowing them to approve the course access enquiry.

These events are listened to by the [escolalms/templates-email](https://github.com/EscolaLMS/Templates-Email/tree/main/src/CourseAccess) package, which sends emails based on its own logic. 
Additionally, notifications are stored in database.

### Course Access 
- `CourseAssigned` and `CourseAccessStarted` - These events are dispatched when a user is assigned access to a course. It contains information about the user and the course to which access has been granted.
- `CourseUnassigned` and `CourseFinished` - These events are dispatched when a user is denied access to a course.

The [escolalms/templates-email](https://github.com/EscolaLMS/Templates-Email/tree/main/src/Courses) package also listens to the above events.

## Course Access Enquiry - sequence diagram

```mermaid
sequenceDiagram

    actor Student
    actor Administrator
    participant FrontApp
    participant AdminPanel
    participant Backend

    Student ->> FrontApp: Send access request
    FrontApp ->> Backend: Forward access request
    Backend ->> Backend: Process access request
    alt Request is valid
        Backend -->> Administrator: Notify about new access request
        Backend -->> Student: Send confirmation
        alt Administrator approves request
            Administrator ->> AdminPanel: Access request approval
            AdminPanel ->> Backend: Approve access request
            Backend ->> Backend: Grant access to course
            Backend ->> Student: Send access confirmation
        else Administrator remove request
            Administrator ->> AdminPanel: Access request remove
            AdminPanel ->> Backend: Remove access request
        else Setting auto_accept_access_request is true
            Backend ->> Backend: Grant access to course
            Backend ->> Student: Send access confirmation
        end
    else Request is invalid
        Backend -->> FrontApp: Send invalid request notification
        FrontApp -->> Student: Forward invalid request notification
    end
    
```

## Permissions

Permissions are defined in [seeder](https://github.com/EscolaLMS/Course-Access/blob/main/database/seeders/CourseAccessPermissionSeeder.php).

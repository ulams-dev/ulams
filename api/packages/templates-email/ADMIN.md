# Admin panel documentation

This package is an extension of the [Template package](https://github.com/EscolaLMS/Templates/blob/main/ADMIN.md) and is used for managing email templates.

Templates are defined in the *Templates* and *Email* tabs. From the template list, you can create, edit or delete a template.

![Templates](https://github.com/EscolaLMS/Templates-Email/assets/59456825/c317bc59-4d20-4307-9f58-5301cb5985ca)

Each email template has a name, an event, and you can set the template to be the default.
Sending an email is only possible for the default template.
The list of events available for selection may vary depending on the installed *escolalms* packages. Each package emits its own events.

![Template form](https://github.com/EscolaLMS/Templates-Email/assets/59456825/30b51f09-4271-4911-a9aa-4fcf955cb330)

Clicking the *Preview* button will send an email to you. The values of the variables will be mocked.

![Preview](https://github.com/EscolaLMS/Templates-Email/assets/59456825/27042b6a-0d9e-4e05-b3d9-4b6b0e1a1797)

When defining a template, you have variables to use. There are two types of variables global and event-defined. Global variables store general, system-related information, personalized variables store information directly related to the event.
Variables use a convention, with the @ sign before the variable name, to use a variable in a template you need to put the @ sign and the variable name, e.g. @VarSimpleName. 

![Variables](https://github.com/EscolaLMS/Templates-Email/assets/59456825/751d01f5-7448-47bd-b77c-836f5f699174)

The variables that are in the *required variables* section are the ones you must use in your template.

![Required variables](https://github.com/EscolaLMS/Templates-Email/assets/59456825/8d8d5282-b74f-4540-883a-67fb3fb250a2)

Mjml is used to create the content of an email.

![Email content](https://github.com/EscolaLMS/Templates-Email/assets/59456825/bc67ee9b-e415-4bb9-8df6-b979daa95b8d)

You can use *Settings* to save recurring parts of the template, e.g. footer or header.  
Just define new variables in the *mail* group with the mjml code and then use those variables in the template.

![Global mail variables](https://github.com/EscolaLMS/Templates-Email/assets/59456825/adb8a588-f3fd-469c-8ed8-fb8667eef776)

![Content with global variables](https://github.com/EscolaLMS/Templates-Email/assets/59456825/4f69fcfe-7a26-40b5-8e03-a3ae2b0b1cd3)


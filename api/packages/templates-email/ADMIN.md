# Admin panel documentation

This package is an extension of the [Template package](https://github.com/EscolaLMS/Templates/blob/main/ADMIN.md) and is used for managing email templates.

Templates are defined in the *Templates* and *Email* tabs. From the template list, you can create, edit or delete a template.

![Templates](docs/img/admin-01.png)

Each email template has a name, an event, and you can set the template to be the default.
Sending an email is only possible for the default template.
The list of events available for selection may vary depending on the installed *ulams* packages. Each package emits its own events.

![Template form](docs/img/admin-02.png)

Clicking the *Preview* button will send an email to you. The values of the variables will be mocked.

![Preview](docs/img/admin-03.png)

When defining a template, you have variables to use. There are two types of variables global and event-defined. Global variables store general, system-related information, personalized variables store information directly related to the event.
Variables use a convention, with the @ sign before the variable name, to use a variable in a template you need to put the @ sign and the variable name, e.g. @VarSimpleName. 

![Variables](docs/img/admin-04.png)

The variables that are in the *required variables* section are the ones you must use in your template.

![Required variables](docs/img/admin-05.png)

Mjml is used to create the content of an email.

![Email content](docs/img/admin-06.png)

You can use *Settings* to save recurring parts of the template, e.g. footer or header.  
Just define new variables in the *mail* group with the mjml code and then use those variables in the template.

![Global mail variables](docs/img/admin-07.png)

![Content with global variables](docs/img/admin-08.png)


## Consultations

Displaying a list of consultations based on criteria. After logging in to the Admin Panel, go to the Consultations tab in the left-side menu. Above the space for the list of potential consultations, you can see three options for displaying them: Date Range, Name, Categories.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/47dece36-6bdc-4f99-a32a-66737072c506)

All filters work in the same way as previously described.
A single consultation record looks like this:

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/fbd5e438-8e34-417b-aa0f-90f0c172e904)

We can see many familiar options and names similar to the Courses view.
* ID - the assigned number for the consultation
* Name - the name of the consultation
* Status - ![image](https://github.com/EscolaLMS/Consultations/assets/108077902/eeb4108c-175b-49d0-a5d7-5e27a59e2494), ![image](https://github.com/EscolaLMS/Consultations/assets/108077902/3cfafe6e-c6cd-4bc3-8a87-c4ce8400dd8c), and ![image](https://github.com/EscolaLMS/Consultations/assets/108077902/ecc13cc6-78ab-464b-892a-e25f651b79a1), similar to the statuses in Courses. An additional status.
* Duration - values can be in minutes or hours. It is recommended that the consultation lasts for one clock hour, but it can also be longer.
* Active From - the date from which the consultations are active and available for purchase on the frontend platform.
* Active Until - the date until which the consultations are active and available for purchase on the frontend platform.
* Categories - assigned during the creation of the consultation in the tab of the same name, following the mechanics mentioned during the course creation phase.

## Adding a new consultation

You can add a new consultation by clicking on the blue button labeled ![image](https://github.com/EscolaLMS/Consultations/assets/108077902/38d30ce0-1315-4085-9184-5c75bba12340).

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/13892ad7-f9f4-406c-9431-8558322dc61b)

Creating a new consultation is straightforward. You need to enter:

* Name (mandatory) - the name of the consultation (usually, this can be the topic being discussed or even the name of the person who will be the trainer).
* Duration - in accordance with the rules described above when discussing this field.
* Status - there are three options to choose from: draft, archived, and published.
* Active from and Active to - as explained earlier when discussing the consultation record table. A date picker appears, and you can set the time frame during which the consultation will be available.
* Trainer (mandatory) - start typing the first and last name of the user with the role of a trainer. Consultations must have a designated trainer, i.e., a person who will conduct the conversation with the student. If such a user with the role of a trainer already exists in the database, click on their name and add them as the trainer of the consultation.
* Description - in this section, you should present the consultation to clients - what it's about, who the facilitator is, what the goal is, and what topics can be discussed in such an online conversation with the trainer. It's a good idea to format everything using formatting methods described in the Formatting in Longer Text Fields subsection.
>[!WARNING]
>The "Proposed Dates" feature works when setting up a product package. If it's a single consultation, it allows users to sign up for any available meeting time regardless of the entered date. For a single consultation, the trainer can either accept, reject, or propose an alternative time. This field is significant for consultation packages with a master meeting date.
* Proposed dates - this is an important calendar for limiting situations, such as clients not scheduling appointments at odd hours. You can set a "schedule" for which days and times the trainer is available for a particular consultation. Not filling in this field means the trainer will be available for consultations 24/7.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/95f0f9ae-5bbd-4c22-9e57-a8cb17d1f6cb)

Upon confirming the addition of a new consultation, additional tabs appear, just like with courses. Some of them are repeated, so they will be skipped. You'll also notice that you are immediately in edit mode for your new consultation.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/45c3ab9e-35da-4756-9b25-350fdefbb160)

* Product, Media, and Categories - these tabs function similarly to courses and should not pose a significant challenge.
* Branding - this is a new tab, but it operates on a very simple principle. If your trainer has their own logo or works on behalf of a known company, you can upload a graphic file with the logo in the *svg format, and it will be visible during consultations in the conversation window on the left side at the top.
* Saving a User Without an Account - we have already discussed this mechanism during the course creation phase.
* Calendar - this tab provides a detailed calendar view of when a consultation is scheduled to take place or has already occurred. It is an important feature for trainers to keep track of the schedules of numerous consultations.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/fa688658-15b2-43ac-a579-93a16211bde5)

## How does it work in practice?

# User View.

An example on the simplest template of the platform's front end looks like this:
![image](https://github.com/EscolaLMS/Consultations/assets/108077902/9b09b09b-42d2-43f7-af81-b98654113c5a)

A logged-in user searches for a consultation on the platform and purchases it. Then, after making the purchase, they go to their profile and navigate to the "Owned Consultations" tab. There, they can find the purchased consultation and schedule a meeting with the trainer.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/811f9dc0-4174-4483-ba2b-2c76174ae32a)

After clicking the save button, a meeting calendar will appear:

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/e0d07455-b273-4ec5-8317-9c804d0c06a9)

After clicking the "Choose" button:

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/479d0953-50c5-4349-b632-88c1af7949a2)

After a successful reservation, a message will appear:

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/534a2396-6cdd-45b9-a530-838ace1cfce7)

And the time slot beneath the consultation will be highlighted in yellow, indicating that it's pending approval or rejection by the trainer.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/fa45f680-5904-4903-9080-4ed337465e2c)

# Trainer View.

The trainer in the admin panel sees the reserved meeting and can either accept it or change the date/time of the meeting and then send the acceptance.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/f945fc2e-d218-4b84-bd86-431e8470ae32)

After the trainer accepts the appointment, the user sees the status changed to green, along with the updated time. Of course, the user can also propose another day and time, repeating the process until a suitable time for both the trainer and the user is found.

![image](https://github.com/EscolaLMS/Consultations/assets/108077902/9c35be01-4e74-48f0-82a5-8de393d22b9d)



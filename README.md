## Reefolio

Reefolio is a web application that automatically converts an uploaded resume into a hosted portfolio website. Users can upload their resume, choose a design template, and the system uses AI to parse the data and populate a professional portfolio.

---

### Features

* **Resume Parsing:** Extracts data from resumes using Google Gemini AI.
* **Template Selection:** Choose from various portfolio layouts.
* **Media Management:** Uploads and storage handled via Cloudinary.
* **Authentication:** Secure login and signup using Supabase OAuth.
* **Responsive Design:** Fully responsive UI built with Tailwind CSS.

---

### Tech Stack

| Layer | Technology |
| :--- | :--- |
| **Frontend** | React, Tailwind CSS, React Router, Framer Motion, Axios |
| **Backend** | Laravel (PHP) |
| **Database & Auth** | Supabase |
| **Cloud Storage** | Cloudinary |
| **Generative AI** | Google Gemini API |

---

### Usage
1. **Log in** using your preferred social account via OAuth.
2. **Upload** your resume (PDF/Docx).
3. **Select** a portfolio template from the available options.
4. **AI Generation:** Wait for Gemini to parse and format your content.
5. **Publish:** Preview your new portfolio and save the link.

---

### 🔗 Frontend Client Code
The user interface and client-side logic for this project are located in a separate repository. You can access it here:
**[Reefolio Frontend Repository](https://github.com/iusmanzubair/Reefolio)**

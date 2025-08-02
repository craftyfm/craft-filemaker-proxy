# FileMaker Proxy for Craft CMS

**Package:** `craftyfm/filemaker-proxy`  
**Craft CMS Plugin**

This plugin acts as a proxy bridge to integrate Craft CMS with [FileMaker](https://www.claris.com/filemaker/), enabling interactions through dummy endpoints. These endpoints are only accessible via `localhost`, which adds a layer of security when used with third-party integrations like **Webhooks**, **Formie**, **Freeform**, **Feed Me**, or **Form Builder**.

---

## 🛠 Installation

1. Require the package via Composer:
   ```bash
   composer require craftyfm/filemaker-proxy 

2. Install the plugin in the Craft CMS control panel or run:

   ```bash
   php craft plugin/install filemaker-proxy
   ```

---

## ⚙️ Plugin Settings

Go to **Settings → FileMaker Proxy** in your Craft control panel to configure:

### 🔐 Connection Credentials

* Create your FileMaker connection credentials under the **Connections** tab.
* These will include host, username, password, and database.

### 📧 Admin Email

* Enter an admin email to receive error reports for failed or unexpected requests.

### 🔑 API Token

* Define a secure **API token** that will be used to authorize incoming requests to the proxy endpoints.

---

## 🧩 Profiles

To handle different integrations, you can create multiple **Profiles**:

1. Navigate to **Profiles** tab.
2. Create a new profile:

    * Select the associated connection.
    * Define the target **Layout** in FileMaker.
    * Toggle **Enable Endpoint**:

        * This allows the profile to expose a dummy endpoint accessible only from `localhost`.
        * In some cases (e.g., Feed Me), this doesn't need to be enabled — the dummy action URL is enough.

---

## 🚀 Usage with Craft Plugins

### 1. **Webhooks Plugin**

* Make sure the profile **endpoint is enabled**.
* Use the generated profile endpoint URL as the **Request URL** in the Webhooks plugin.
* Add a custom header for authorization:

  ```
  Authorization: Bearer {{ getenv('TOKEN') }}
  ```

  Replace `TOKEN` with the API token you set in the plugin settings.

### 2. **Feed Me Plugin**

* Set the profile as **enabled** (endpoint does not need to be enabled).
* Add the profile’s endpoint URL as the **feed URL**.
* When Feed Me initiates a request, it will automatically be intercepted and forwarded to FileMaker through the defined connection.

### 3. **Formie Plugin**

* Create a custom integration under the **Miscellaneous** type.
* Select and configure the appropriate FileMaker profile within your form.

### 4. **Freeform Plugin**

* Create a custom integration under the **Other** type.
* Configure it similarly to Formie by linking to a FileMaker profile.

### 5. **Form Builder Plugin**

* Create a custom integration under the **Miscellaneous** type.
* Configure the FileMaker settings within the form setup.

---

## 🔒 Security Notes

* Only requests from `localhost` can trigger real interactions with FileMaker.
* All endpoints are protected using API tokens defined in your plugin settings.
* Dummy endpoints help isolate external plugins from real FileMaker operations unless explicitly enabled.

---

## 📬 Support

For issues or feature requests, please open an issue in the repository or contact the maintainer.

---


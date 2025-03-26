// main.js
// Production-ready General / Core Functions for Writepathic

// Import CryptoJS from the CDN
import CryptoJS from 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js';

/**
 * Initialize the application:
 * Sets up global event listeners, common UI components, etc.
 */
export function initApp() {
  // Example: Listen for DOMContentLoaded to initialize components
  document.addEventListener('DOMContentLoaded', () => {
    console.log("Writepathic App Initialized.");
    // Additional startup routines can be added here.
  });
}

/**
 * Make an AJAX request.
 * @param {string} method - HTTP method (GET, POST, etc.).
 * @param {string} url - The target URL.
 * @param {Object|null} data - Data to send (for POST, etc.).
 * @param {Function} callback - Callback function to process the response.
 */
export function ajaxRequest(method, url, data, callback) {
  const xhr = new XMLHttpRequest();
  xhr.open(method, url, true);
  xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === XMLHttpRequest.DONE) {
      if (xhr.status >= 200 && xhr.status < 300) {
        let response = xhr.responseText;
        try {
          response = JSON.parse(response);
        } catch (e) {
          // If response is not JSON, leave as is.
        }
        callback(null, response);
      } else {
        callback(new Error("AJAX request failed with status " + xhr.status));
      }
    }
  };

  xhr.send(data ? JSON.stringify(data) : null);
}

/**
 * Generate a unique ID with a given prefix.
 * @param {string} prefix - A string prefix (e.g., "p", "u").
 * @returns {string} - A unique identifier.
 */
export function generateUniqueID(prefix = "") {
  // Use current time + random number combination.
  return prefix + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}

/**
 * Display a notification message.
 * @param {string} message - The message to display.
 * @param {string} [type="info"] - Notification type ("info", "success", "error").
 */
export function displayNotification(message, type = "info") {
  // Create a container for notifications if it doesn't exist
  let container = document.getElementById("notification-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "notification-container";
    container.style.position = "fixed";
    container.style.top = "10px";
    container.style.right = "10px";
    container.style.zIndex = "1000";
    document.body.appendChild(container);
  }

  const notification = document.createElement("div");
  notification.textContent = message;
  notification.style.marginBottom = "10px";
  notification.style.padding = "10px 20px";
  notification.style.borderRadius = "5px";
  notification.style.color = "#fff";
  notification.style.boxShadow = "0 2px 4px rgba(0,0,0,0.2)";
  notification.style.opacity = "0.9";

  // Set background color based on type
  switch (type) {
    case "success":
      notification.style.backgroundColor = "#28a745";
      break;
    case "error":
      notification.style.backgroundColor = "#dc3545";
      break;
    default:
      notification.style.backgroundColor = "#007bff";
  }

  container.appendChild(notification);

  // Remove the notification after 4 seconds
  setTimeout(() => {
    notification.remove();
  }, 4000);
}

/**
 * Hash a string using CryptoJS.
 * @param {string} input - The string to hash.
 * @returns {string} - The hashed result.
 */
export function hashString(input) {
  return CryptoJS.SHA256(input).toString();
}

/**
 * Encrypt data using CryptoJS AES.
 * @param {Object|string} data - Data to encrypt.
 * @param {string} secretKey - The secret key for encryption.
 * @returns {string} - The encrypted ciphertext.
 */
export function encryptData(data, secretKey) {
  const dataString = typeof data === "object" ? JSON.stringify(data) : data;
  return CryptoJS.AES.encrypt(dataString, secretKey).toString();
}

/**
 * Decrypt data using CryptoJS AES.
 * @param {string} ciphertext - The encrypted data.
 * @param {string} secretKey - The secret key for decryption.
 * @returns {Object|string} - The decrypted data.
 */
export function decryptData(ciphertext, secretKey) {
  const bytes = CryptoJS.AES.decrypt(ciphertext, secretKey);
  const decrypted = bytes.toString(CryptoJS.enc.Utf8);
  try {
    return JSON.parse(decrypted);
  } catch (e) {
    return decrypted;
  }
}

// Initialize the app once the module is loaded.
initApp();

/**
 * Hash a string using CryptoJS SHA-256.
 * Useful for hashing passwords or other sensitive data before sending them to the server.
 * @param {string} input - The string to hash.
 * @returns {string} - The resulting SHA-256 hash in hexadecimal format.
 */
export function hashString(input) {
    return CryptoJS.SHA256(input).toString();
  }
  
  /**
   * Encrypt data using CryptoJS AES.
   * The function accepts either a JSON object or a string and encrypts it using the provided secret key.
   * @param {Object|string} data - The data to encrypt.
   * @param {string} secretKey - The secret key used for encryption.
   * @returns {string} - The encrypted ciphertext.
   */
  export function encryptData(data, secretKey) {
    const dataString = typeof data === "object" ? JSON.stringify(data) : data;
    return CryptoJS.AES.encrypt(dataString, secretKey).toString();
  }
  
  /**
   * Decrypt data using CryptoJS AES.
   * This function takes an encrypted ciphertext and a secret key to decrypt the data.
   * It attempts to parse the decrypted text as JSON; if parsing fails, the raw decrypted string is returned.
   * @param {string} ciphertext - The encrypted data to decrypt.
   * @param {string} secretKey - The secret key used for decryption.
   * @returns {Object|string} - The decrypted data, parsed as JSON if possible.
   */
  export function decryptData(ciphertext, secretKey) {
    const bytes = CryptoJS.AES.decrypt(ciphertext, secretKey);
    const decrypted = bytes.toString(CryptoJS.enc.Utf8);
    try {
      return JSON.parse(decrypted);
    } catch (e) {
      return decrypted;
    }
  }

// Functions for Student Interface

/**
 * loadEnrolledClasses
 * Fetch and display the list of classes the student is enrolled in.
 * Expects core.php to return an array of class objects.
 */
export function loadEnrolledClasses() {
    ajaxRequest("GET", "core.php?action=getEnrolledClasses", null, (err, data) => {
      if (err) {
        displayNotification("Failed to load enrolled classes: " + err.message, "error");
      } else {
        // Assuming 'data' is an array of classes; update the DOM accordingly.
        const container = document.getElementById("class-list-container");
        if (container) {
          container.innerHTML = "";
          data.forEach((cls) => {
            const div = document.createElement("div");
            div.className = "class-card";
            div.innerHTML = `
              <h3>${cls.class_name}</h3>
              <p>Enrollment Code: <strong>${cls.enrollment_code}</strong></p>
            `;
            // Optionally, add an event to load class content when clicked.
            div.addEventListener("click", () => loadClassContent(cls.class_id));
            container.appendChild(div);
          });
        }
      }
    });
  }
  
  /**
   * enrollInClass
   * Send an enrollment code to core.php and handle the response.
   * @param {string} enrollmentCode - The enrollment code input by the student.
   */
  export function enrollInClass(enrollmentCode) {
    const data = { enrollmentCode: enrollmentCode };
    ajaxRequest("POST", "core.php?action=enrollInClass", data, (err, response) => {
      if (err) {
        displayNotification("Enrollment failed: " + err.message, "error");
      } else {
        if (response.success) {
          displayNotification("Successfully enrolled in class!", "success");
          loadEnrolledClasses(); // Refresh class list.
        } else {
          displayNotification("Enrollment error: " + response.message, "error");
        }
      }
    });
  }
  
  /**
   * loadClassContent
   * Retrieve and render class posts, assignments, and other content for a selected class.
   * @param {string} class_id - The ID of the selected class.
   */
  export function loadClassContent(class_id) {
    const data = { class_id: class_id };
    ajaxRequest("POST", "core.php?action=getClassContent", data, (err, response) => {
      if (err) {
        displayNotification("Failed to load class content: " + err.message, "error");
      } else {
        // Example: assume response contains HTML content.
        const contentContainer = document.getElementById("class-content-container");
        if (contentContainer) {
          contentContainer.innerHTML = response.htmlContent || "";
        }
      }
    });
  }
  
  /**
   * toggleClassContent
   * Handle expanding/collapsing class content sections.
   * @param {string} class_id - The ID of the class to toggle.
   */
  export function toggleClassContent(class_id) {
    const section = document.getElementById("class-content-" + class_id);
    if (section) {
      section.style.display = (section.style.display === "block") ? "none" : "block";
    }
  }
  
  /**
   * validateFileUpload
   * Validate the file size and optionally its type before submitting an assignment.
   * @param {File} file - The File object from an input.
   * @param {number} maxSize - Maximum allowed size in bytes.
   * @returns {Object} - { valid: boolean, message: string }
   */
  export function validateFileUpload(file, maxSize) {
    if (!file) {
      return { valid: false, message: "No file provided." };
    }
    if (file.size > maxSize) {
      return {
        valid: false,
        message: "File size exceeds the maximum allowed (" + (maxSize / (1024 * 1024)) + " MB)."
      };
    }
    // Additional validation (e.g., file type) can be added here.
    return { valid: true, message: "File is valid." };
  }
  
  /**
   * submitAssignment
   * Send assignment submission data (text and file) to core.php.
   * @param {Object} assignmentData - Object containing:
   *    assignment_id (string),
   *    text_response (string),
   *    file (File object, optional)
   */
  export function submitAssignment(assignmentData) {
    // Create FormData for file upload support.
    const formData = new FormData();
    formData.append("assignment_id", assignmentData.assignment_id);
    formData.append("text_response", assignmentData.text_response);
    if (assignmentData.file) {
      formData.append("file_response", assignmentData.file);
    }
  
    // Using XMLHttpRequest for FormData submission.
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "core.php?action=submitAssignment", true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState === XMLHttpRequest.DONE) {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
              displayNotification("Assignment submitted successfully!", "success");
              // Optionally update the UI or redirect the user.
            } else {
              displayNotification("Submission error: " + response.message, "error");
            }
          } catch (e) {
            displayNotification("Invalid server response.", "error");
          }
        } else {
          displayNotification("Assignment submission failed. Status: " + xhr.status, "error");
        }
      }
    };
    xhr.send(formData);
  }

// Functions for Instructor Interface

/**
 * loadInstructorClasses
 * Fetch and display all classes created by the instructor.
 * Expects core.php to return an array of class objects.
 */
export function loadInstructorClasses() {
    ajaxRequest("GET", "core.php?action=getInstructorClasses", null, (err, data) => {
      if (err) {
        displayNotification("Failed to load your classes: " + err.message, "error");
      } else {
        // Assuming 'data' is an array of class objects.
        const container = document.getElementById("instructor-class-list");
        if (container) {
          container.innerHTML = "";
          data.forEach((cls) => {
            const card = document.createElement("div");
            card.className = "class-card";
            card.innerHTML = `
              <h3>${cls.class_name}</h3>
              <p>Enrollment Code: <strong>${cls.enrollment_code}</strong></p>
            `;
            // When clicked, toggle the dashboard view for the class.
            card.addEventListener("click", () => toggleDashboardView(cls.class_id));
            container.appendChild(card);
          });
        }
      }
    });
  }
  
  /**
   * toggleDashboardView
   * Show or hide the detailed dashboard view for a specific class.
   * @param {string} class_id - The ID of the class to toggle.
   */
  export function toggleDashboardView(class_id) {
    const dashboardSection = document.getElementById("dashboard-" + class_id);
    if (dashboardSection) {
      // Toggle display between block and none.
      dashboardSection.style.display = (dashboardSection.style.display === "block") ? "none" : "block";
    }
  }
  
  /**
   * searchStudents
   * Filter the list of enrolled students based on a search query.
   * @param {string} query - The search string entered by the instructor.
   * @param {string} class_id - The class whose student list should be filtered.
   */
  export function searchStudents(query, class_id) {
    const filter = query.toLowerCase();
    const studentList = document.getElementById("student-list-" + class_id);
    if (studentList) {
      const students = studentList.getElementsByTagName("li");
      Array.from(students).forEach(student => {
        const name = student.textContent || student.innerText;
        student.style.display = name.toLowerCase().indexOf(filter) > -1 ? "" : "none";
      });
    }
  }
  
  /**
   * openSubmissionModal
   * Open a modal popup to display assignment submission details.
   * @param {string} assignmentId - The ID of the assignment.
   */
  export function openSubmissionModal(assignmentId) {
    // Call fetchSubmissions to load submission details.
    fetchSubmissions(assignmentId, (err, submissionsHtml) => {
      if (err) {
        displayNotification("Failed to load submissions: " + err.message, "error");
      } else {
        const modalBody = document.getElementById("submissionTableBody");
        if (modalBody) {
          modalBody.innerHTML = submissionsHtml;
          // Display the modal.
          document.getElementById("submissionModal").style.display = "block";
        }
      }
    });
  }
  
  /**
   * fetchSubmissions
   * AJAX function to fetch submission data for an assignment from core.php.
   * @param {string} assignmentId - The ID of the assignment.
   * @param {Function} callback - Callback to handle the response.
   */
  export function fetchSubmissions(assignmentId, callback) {
    const data = { assignment_id: assignmentId };
    ajaxRequest("POST", "core.php?action=fetchSubmissions", data, (err, response) => {
      if (err) {
        callback(err, null);
      } else {
        // Assuming response.submissions contains an array of submission objects,
        // and that our server returns HTML to render into the modal.
        // If not, you can loop over response.submissions to generate table rows.
        if (response.success && response.submissions) {
          let html = "";
          response.submissions.forEach(submission => {
            // Find student name; assume submission has student_name property.
            html += `
              <tr>
                <td>${submission.student_name}</td>
                <td><a href="${submission.file_url}" target="_blank">${submission.file_name}</a></td>
              </tr>
            `;
          });
          callback(null, html);
        } else {
          callback(new Error(response.message || "No submissions found"), null);
        }
      }
    });
  }

// Post Management Functions for post.php

// Maximum allowed content elements per post.
const maxElements = 10;

/**
 * addContentElement
 * Dynamically add a new content element block to the post form.
 * This block allows the instructor to choose from types: text, image, video, file, link, assignment.
 */
export function addContentElement() {
  const container = document.getElementById("content-elements-container");
  if (!container) return;
  
  const currentElements = container.querySelectorAll(".content-element").length;
  if (currentElements >= maxElements) {
    displayNotification(`Maximum of ${maxElements} content elements allowed.`, "error");
    return;
  }
  
  const index = currentElements;
  const elementHTML = `
    <div class="content-element" data-index="${index}">
      <label>Content Type:</label>
      <select name="content_type[]" onchange="toggleContentFields(this, ${index})">
        <option value="">--Select Type--</option>
        <option value="text">Text</option>
        <option value="image">Image</option>
        <option value="video">Video</option>
        <option value="file">File</option>
        <option value="link">Link</option>
        <option value="assignment">Assignment</option>
      </select>
      <div class="fields-container" id="fields-${index}"></div>
      <span class="remove-btn" onclick="removeContentElement(this)">Remove</span>
    </div>
  `;
  container.insertAdjacentHTML("beforeend", elementHTML);
}

/**
 * removeContentElement
 * Remove a content element block from the post form.
 * @param {HTMLElement} element - The clicked remove button.
 */
export function removeContentElement(element) {
  const parent = element.closest(".content-element");
  if (parent) {
    parent.remove();
  }
}

/**
 * toggleContentFields
 * Show or hide additional input fields based on the selected content type.
 * For example, if "assignment" is selected, show extra inputs for assignment title,
 * description, deadline, and additional instructions.
 *
 * @param {HTMLSelectElement} selectElement - The select element that changed.
 * @param {number} index - The index of the content element.
 */
export function toggleContentFields(selectElement, index) {
  const container = document.getElementById(`fields-${index}`);
  if (!container) return;
  
  const type = selectElement.value;
  let html = "";
  
  switch (type) {
    case "text":
      html = '<textarea name="content_text[]" placeholder="Enter text..."></textarea>';
      break;
    case "link":
      html = '<input type="url" name="content_link[]" placeholder="Enter URL...">';
      break;
    case "image":
    case "video":
    case "file":
      html = '<input type="file" name="content_file[]" accept="*/*">';
      break;
    case "assignment":
      html = '<input type="text" name="content_assignment_title[]" placeholder="Assignment Title"><br>';
      html += '<textarea name="content_assignment_description[]" placeholder="Assignment Description..."></textarea><br>';
      html += '<input type="datetime-local" name="content_deadline[]"><br>';
      html += '<textarea name="content_text[]" placeholder="Additional instructions (optional)"></textarea>';
      break;
    default:
      html = "";
  }
  
  container.innerHTML = html;
}

/**
 * submitPost
 * Gather all post form data and send it to core.php via AJAX so that the core can
 * write the data into the database. This function uses FormData to support file uploads.
 *
 * @param {HTMLFormElement} formElement - The post form element.
 */
export function submitPost(formElement) {
  // Create FormData from the form element
  const formData = new FormData(formElement);
  
  // Create an XMLHttpRequest for FormData submission.
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "core.php?action=savePost", true);
  
  xhr.onreadystatechange = function () {
    if (xhr.readyState === XMLHttpRequest.DONE) {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            displayNotification("Post saved successfully!", "success");
            // Optionally, redirect or update the UI.
          } else {
            displayNotification("Failed to save post: " + response.message, "error");
          }
        } catch (e) {
          displayNotification("Invalid server response.", "error");
        }
      } else {
        displayNotification("Post submission failed. Status: " + xhr.status, "error");
      }
    }
  };
  
  xhr.send(formData);
}

/**
 * sendDataToCore
 * Centralize sending data to core.php for any create/update operations.
 * @param {string} endpoint - The endpoint/action to call (e.g., "savePost", "enrollInClass").
 * @param {Object} data - Data to be sent.
 * @param {Function} callback - Callback to process the server response.
 */
export function sendDataToCore(endpoint, data, callback) {
    // Here we use our general ajaxRequest helper function defined earlier.
    ajaxRequest("POST", `core.php?action=${endpoint}`, data, (err, response) => {
      if (err) {
        callback(err, null);
      } else {
        callback(null, response);
      }
    });
  }
  
  /**
   * receiveDataFromCore
   * Process data received from core.php (e.g., perform data transformation or error handling).
   * @param {Object} response - The response data from core.php.
   * @returns {Object|null} - Processed data, or null if an error is detected.
   */
  export function receiveDataFromCore(response) {
    // Example: Check for an error flag or transform the data as needed.
    if (response.error) {
      handleAjaxError(new Error(response.error));
      return null;
    }
    // Optionally, perform transformations on response.data.
    return response.data || response;
  }
  
  /**
   * formatDate
   * Convert an ISO 8601 date string to a human-readable format.
   * @param {string} isoDate - ISO 8601 date string.
   * @returns {string} - Formatted date.
   */
  export function formatDate(isoDate) {
    const date = new Date(isoDate);
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString(undefined, options);
  }
  
  /**
   * clearForm
   * Reset form fields after a successful submission or on cancel.
   * @param {HTMLFormElement} formElement - The form element to be cleared.
   */
  export function clearForm(formElement) {
    if (formElement && typeof formElement.reset === 'function') {
      formElement.reset();
    }
  }
  
  /**
   * handleAjaxError
   * Standardized error handling for AJAX requests.
   * @param {Error} error - The error object.
   */
  export function handleAjaxError(error) {
    console.error("AJAX Error: ", error);
    displayNotification("An error occurred: " + error.message, "error");
  }
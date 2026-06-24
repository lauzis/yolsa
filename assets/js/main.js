if (!chatGptSoeIdsChecked) {
  var chatGptSoeIdsChecked = [];
}

if (!chatGptSoeIdsToAudit) {
  var chatGptSoeIdsToAudit = [];
}


function httpPost(url, headers, data, callback, failCallBack) {

  var xmlhttp = new XMLHttpRequest();   // new HttpRequest instance
  xmlhttp.onreadystatechange = function () {
    if (xmlhttp.readyState === XMLHttpRequest.DONE) { // XMLHttpRequest.DONE == 4
      if (xmlhttp.status === 200) {
        callback(JSON.parse(xmlhttp.response), url, headers);
      } else {
        if (failCallBack) {
          failCallBack();
        }
      }
      clearTimeout(window.requestCancelTimer);
    }
  };
  xmlhttp.open("POST", url, true);
  xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
  xmlhttp.setRequestHeader('X-WP-Nonce', chatGptSeoNonce.chatGptSeoNonce);
  if (headers && headers.headers) {
    for (const key of Object.keys(headers.headers)) {
      xmlhttp.setRequestHeader(key, headers.headers[key]);
    }
  }
  xmlhttp.send(JSON.stringify(data));
}


function httpGet(url, headers, callback, failCallBack) {
  var xmlhttp = new XMLHttpRequest();

  xmlhttp.onreadystatechange = function () {
    if (xmlhttp.readyState === XMLHttpRequest.DONE) { // XMLHttpRequest.DONE == 4

      const status = xmlhttp.status;
      if (status === 200) {
        try {
          callback(JSON.parse(xmlhttp.response), url, headers, status);
        } catch (e) {
          console.log(e);
          callback({response: xmlhttp.response}, url, headers, status);
        }

      } else if (status === 204) {
        callback(null, url, headers, status);
      } else if (status === 401) {
        callback(null, url, headers, status);
      } else {
        if (failCallBack) {
          failCallBack(null, url, headers, status);
        }
      }
      clearTimeout(window.requestCancelTimer);
    }
  };

  xmlhttp.open("GET", url, true);
  xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
  xmlhttp.setRequestHeader('X-WP-Nonce', chatGptSeoNonce.chatGptSeoNonce);
  if (headers && headers.headers) {
    for (const headersKey of Object.keys(headers.headers)) {
      xmlhttp.setRequestHeader(headersKey, headers.headers[headersKey]);
    }
  }

  xmlhttp.send();
}

function checkStatus() {
  var checked_count = chatGptSoeIdsChecked.length;
  var total = chatGptSoeIdsToAudit.length + chatGptSoeIdsChecked.length;

  var percents = Math.floor(checked_count / total * 100);

  if (checked_count === total) {
    buttonClearAuditEnable();
  }
  var statusElement = document.querySelector('.chat-gpt-seo-status');
  if (statusElement) {
    statusElement.style.width = percents + "%";
    statusElement.innerHTML = checked_count + " / " + total + " ( " + percents + "% )";
  }
}

function crawlData(passId) {


  var element = document.querySelector('.chat-gpt-seo-check-post');
  var id = passId;
  if (element) {
    id = element.getAttribute('data-id');
    element.classList.remove('chat-gpt-seo-check-post');
    element.classList.add('chat-gpt-seo-check-checking-' + id);
  } else if (chatGptSoeIdsToAudit.length > 0) {
    id = chatGptSoeIdsToAudit.pop();
  }
  checkStatus();

  var force = false;
  if (passId) {
    force = true;
  }

  var url = "/wp-json/seo-audit/v1/audit-item/" + id;
  if (force) {
    url = "/wp-json/seo-audit/v1/force-audit-item/" + id;
  }

  var status = document.querySelector('#penalty-' + id);
  if (status) {
    status.innerHTML = "...auditing...";
  }

  httpGet(url, null, (data) => {
    var element = document.querySelector('.chat-gpt-seo-check-checking-' + data.id);
    if (element) {
      element.classList.remove('chat-gpt-seo-check-checking-' + data.id);
      element.classList.add("chat-gpt-seo-report-done");
    }
    if (status) {
      status.innerHTML = "...done...";
    }
    chatGptSoeIdsChecked.push(data.id);


    var firstRow = document.querySelector('#seo-summary-' + data.id);
    if (firstRow) {
      firstRow.innerHTML = data.html.first_row_html;
    }
    element = document.querySelector('.chat-gpt-seo-check-post');

    if ((element && !force) || chatGptSoeIdsToAudit.length > 0) {
      crawlData();
    }
  });
}

function toggleBackground(show) {
  var background = document.querySelector('.cgs-modal-bg');
  if (show) {
    background.classList.add('cgs--show');
  } else {
    background.classList.remove('cgs--show');
  }
}

function expandReport(id) {
  var element = document.querySelector('#cgs-more-details-' + id);
  if (element) {
    element.classList.toggle('cgs--show');
    if (element.classList.contains('cgs--show')) {
      toggleBackground(true);
    } else {
      toggleBackground(false);
    }
  }
}

function expandReportClose(id){
  var element = document.querySelector('#cgs-more-details-' + id);
  if (element) {
    element.classList.toggle('cgs--show');
    if (element.classList.contains('cgs--show')) {
      toggleBackground(true);
    } else {
      toggleBackground(false);
    }
  }
}

function reAudit(id) {
  crawlData(id);
}

function getFormDate(id) {
  const form = document.querySelector('#seo-description-form-' + id);
  const formData = new FormData(form);

  var formObject = {};
  formData.forEach((value, key) => {
    // Reflect.has in favor of: object.hasOwnProperty(key)
    if (!Reflect.has(formObject, key)) {
      formObject[key] = value;
      return;
    }
    if (!Array.isArray(formObject[key])) {
      formObject[key] = [formObject[key]];
    }
    formObject[key].push(value);
  });

  return formObject;
}

function disableTextField(id) {
  var textField = document.querySelector('#seo-description-' + id);
  textField.setAttribute('readonly', 'readonly');
  textField.setAttribute('disabled', 'disabled');
}

function enableTextField(id) {
  var textField = document.querySelector('#seo-description-' + id);
  textField.removeAttribute('readonly');
  textField.removeAttribute('disabled');
}

function updateMetaDescription(id) {
  var formData = getFormDate(id);
  var url = "/wp-json/seo-audit/v1/update-meta-description/" + id;

  disableTextField(id);

  var button = document.querySelector('#update-meta-description-button-' + id);
  if (button) {
    button.innerHTML = "Updating..."
    button.setAttribute('disabled', 'disabled');
  }


  function resetButtonText(id) {
    var button = document.querySelector('#update-meta-description-button-' + id);
    if (button) {
      button.innerHTML = button.getAttribute('data-original-button-text');
      button.removeAttribute('disabled');
    }
  }

  httpPost(url, false, formData, function (data) {
    var button = document.querySelector('#update-meta-description-button-' + id);
    if (button){
      enableTextField(id);
      button.innerHTML = "Updated";
      setTimeout(function () {
        resetButtonText(id);
      }, 1000);
    }


  }, function (data) {
    enableTextField(id);
    if (button){
      button.innerHTML = "Failed :(";
    }
    resetButtonText(id);
  });
}

function generateMetaDescription(id) {
  var generateButton = document.querySelector('#generate-button-' + id);
  var originalText = generateButton.innerHTML;
  generateButton.innerHTML = "Generating...";
  var formData = getFormDate(id);
  var url = "/wp-json/seo-audit/v1/generate-meta-description/" + id;
  generateButton.setAttribute('disabled', 'disabled');

  disableTextField(id);

  const header = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': chatGptSeoNonce.chatGptSeoNonce,
  }
  httpPost(url, header, formData, function (data) {
    var metaDescription = data.response;
    var metaDescriptionField = document.querySelector('#seo-description-' + id);
    metaDescriptionField.value = metaDescription;
    generateButton.innerHTML = originalText;
    generateButton.removeAttribute('disabled');
    enableTextField(id)
    generateButton.innerHTML = 'Generated!';
    setTimeout(function () {
      generateButton.innerHTML = originalText;
    }, 1000)

  }, function (data) {
    generateButton.innerHTML = 'Failed ;(';
    setTimeout(function () {
      generateButton.innerHTML = originalText;
    }, 1000)

  });
}

var modalBg = document.querySelector('.cgs-modal-bg');
if (modalBg) {
  document.querySelector('.cgs-modal-bg').addEventListener('click', function () {
    toggleBackground(false);
    var modals = document.querySelectorAll('.cgs--show');
    modals.forEach(function (item) {
      item.classList.remove('cgs--show');
    })
  });
}


function buttonStartAuditDisable() {
  var button = document.getElementById('yolsa-button-start-audit');
  if (button){
    button.setAttribute('disabled', 'disabled');
    button.style.opacity = '0.5';
  }

}

function buttonClearAuditDisable() {
  var button = document.getElementById('yolsa-button-clear-audit');
  if (button){
    button.setAttribute('disabled', 'disabled');
    button.style.opacity = '0.5';
  }
}

function buttonClearAuditEnable() {
  var button = document.getElementById('yolsa-button-clear-audit');
  if (button) {
    button.removeAttribute('disabled');
    button.style.opacity = '1';
  }
}

function init() {
  checkStatus();
  if (chatGptSoeIdsToAudit.length === 0) {
    buttonStartAuditDisable();
  }
  if (chatGptSoeIdsChecked.length === 0) {
    buttonClearAuditDisable();
  }

  jQuery('.chat-gpt-seo-table').DataTable(
   {
     "columnDefs": [
       {"orderable": false, "targets": [3, 4, 5, 6]}
     ]
   }
  );

  jQuery('.yolsa-keywords-table').DataTable();
}

jQuery(document).ready(function () {
  init();
});


function showKeywordPages(id) {
  var element = document.querySelector('#cgs-keywords-links--' + id);
  var showLink = document.querySelector('#cgs-keywords-links-show--' + id);
  var hideLink = document.querySelector('#cgs-keywords-links-hide--' + id);

  if (element) {
    element.classList.remove('cgs--hide');
    if (showLink) {
      showLink.classList.add('cgs--hide');
    }
    if (hideLink) {
      hideLink.classList.remove('cgs--hide');
    }
  }
}

function hideKeywordPages(id) {
  var element = document.querySelector('#cgs-keywords-links--' + id);
  var showLink = document.querySelector('#cgs-keywords-links-show--' + id);
  var hideLink = document.querySelector('#cgs-keywords-links-hide--' + id);

  if (element) {
    element.classList.add('cgs--hide');
    if (showLink) {
      showLink.classList.remove('cgs--hide');
    }
    if (hideLink) {
      hideLink.classList.add('cgs--hide');
    }
  }
}

function yolsaStartAudit() {
  buttonStartAuditDisable();
  buttonClearAuditDisable();
  crawlData();
}

function yolsaClearAuditData() {
  var url = "/wp-json/seo-audit/v1/clear-audit-data";
  httpGet(url, null, function () {
    window.location.reload();
  }, function () {
    window.location.reload();
  });
}



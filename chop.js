function chop_attendance_class () {
  /* public member vars */
  this.url = "http://www.bcctv.org/api/v4/chop.php";
  this.site = "";
  this.token = "";
  this.event_id = "";
  this.intervalHandle = "";
  /* private vars */
  var key = "";
  var isValid_value = false;
  /* public member functions */
  this.init = function (options) {
    if (options.site) this.site = options.site;
    if (options.key) key = options.key;
    // send init to server
    getToken(this);
  }
  this.isValid = function() {
    return isValid_value;
  }
  this.log = function() {
    jQuery.post(
      this.url,
      getVars(this),
      function(data) {
        if (data.response == "success") {
        } else if (data.response == "reauthenticate") {
          getToken(this);
        }
      }
    );
  }
  this.register = function() {
    var site_id = ChopFrontend.event.get('organizationId');
    jQuery.post(
      this.url+'?a=register',
      {site_id: site_id},
      function(data) {
        alert('Your site registration request has successfully been received.  You will receive an email with further instructions.');
      }
    );
  }
  /* private helper functions */
  var getToken = function(obj) {
    var that = obj;
    obj.event_id = ChopFrontend.event.attributes.eventTimeId;
    jQuery.ajax({
      url: obj.url,
      method: "GET",
      that: obj,
      data: { site:obj.site, key:key, event_id:obj.event_id },
      success: function(data) {
        if (data.response == "options"){
        }
        if (data.response == "token" && data.token) {
          this.that.token = data.token;
          this.that.intervalHandle = setInterval(
            function() {
              obj.log()
            },
            60000
          );
        } else {
          console.log ("Problem instantiating chop attendance module for " + this.site);
        }
      },
      error: function() {
        console.log('uh oh! error. please contact your site administrator.');
      },
      dataType: "json"
    });
  }
  var getVars = function(obj) {
    var isLoggedIn = (ChopFrontend.account && identity.attributes.userId);
    var data = {
      token: obj.token,
      session_id: ChopFrontend.sessionId,
      site_id: ChopFrontend.event.get('organizationId'),
      event_id: ChopFrontend.event.attributes.eventTimeId,
      event_isLive: ChopFrontend.event.attributes.isLive,
      user_id: identity.attributes.userId,
      nickname: identity.attributes.nickname,
      ip: identity.attributes.clientIp,
      email: (isLoggedIn ? ChopFrontend.account.model.attributes.email : null),
      firstName: (isLoggedIn ? ChopFrontend.account.model.attributes.firstName : null),
      fullName: (isLoggedIn ? ChopFrontend.account.model.attributes.fullName : null),
      lastLogin: (isLoggedIn ? ChopFrontend.account.model.attributes.lastLogin : null),
      referrer: document.referrer
    };
    return data;
  }
}


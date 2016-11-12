// Data structure initialization
bcctv = {};
bcctv.player = {};
bcctv.ui = {};
bcctv.data = {};
bcctv.data.last = null;
bcctv.data.isLocal = false;
bcctv.CONSTANT = {};
bcctv.CONSTANT.error = -1;
bcctv.CONSTANT.normal = 0;
bcctv.CONSTANT.defaultWidth = 640;
bcctv.CONSTANT.defaultChannel = 3;

//
// API initialization
//
bcctv.jq=jQuery;

//
// bcctv.player
//

bcctv.player.init = function(options) {
  bcctv.jq.getScript("http://content.jwplatform.com/libraries/b0yd0HGu.js", function() { bcctv.player.load(options); });

  // GA
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-30909077-1', 'auto');
  ga('send', 'pageview');

  // Tracking
  (function() {
    window._pa = window._pa || {};
    // _pa.orderId = "myOrderId"; // OPTIONAL: attach unique conversion identifier to conversions
    // _pa.revenue = "19.99"; // OPTIONAL: attach dynamic purchase values to conversions
    // _pa.productId = "myProductId"; // OPTIONAL: Include product ID for use with dynamic ads
    var pa = document.createElement('script'); pa.type = 'text/javascript'; pa.async = true;
    pa.src = ('https:' == document.location.protocol ? 'https:' : 'http:') + "//tag.marinsm.com/serve/560da6ab5813459235000155.js";
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(pa, s);
  })();

}

bcctv.player.load = function(options) {
  // parameters
  var element = options.element;
  var width = options.width;
  var channel = options.channel;
  var directSource = options.directSource;
  var callback = options.callback;
  var playlist = options.playlist;

  // defaults
  if (! width) width = bcctv.CONSTANT.defaultWidth;
  if (! channel) channel = bcctv.CONSTANT.defaultChannel;
  bcctv.data.state = bcctv.CONSTANT.normal;
  if (bcctv.data.retryTimer) clearTimeout(bcctv.data.retryTimer);

  // data
  bcctv.data.element = element;
  bcctv.data.channel = channel;
  bcctv.data.playlist = playlist;

  $('#'+element)
    .wrap( "<div class='"+element+"-container'></div>" )
    .html( "<img src='http://www.bcctv.org/files/bcctv-aspect-blank.png' width='100%' />" )
    .parent()
    .css( "max-width", width + "px" );

  if (navigator.userAgent.match(/android/i) != null){
    bcctv.player.android(element, bcctv.player.getSources());
  } else {

    jwplayer(element).setup({
      image: bcctv.player.getImage(),
      logo: bcctv.player.getLogo(),
      abouttext: 'About BCCTV',
      aboutlink: 'http://www.bcctv.org/',
      sources: bcctv.player.getSources(),
      playlist: (bcctv.data.playlist ? bcctv.data.playlist : null),
      autostart: "true"
    });

  }
  jwplayer(element).onReady(callback);
  jwplayer(element).onError(function() {
/*
    bcctv.data.state = bcctv.CONSTANT.error;
    jwplayer(element).load([{file:"http://www.bcctv.org/stinger.mp4"}]);
    jwplayer(element).play(true);
    bcctv.data.retryTimer = setTimeout (bcctv.player.retry, 300000);
*/
  });

  $('.'+element+'-container').append('<style type="text/css">.'+element+'-container { border:solid black 1px; vertical-align:bottom; }' +
  '.jw-controlbar .jw-icon-prev, .jw-controlbar .jw-icon-next, .jw-controlbar .jw-icon-playlist { display:none; }');

  bcctv.ui.showChannels();


  if (directSource) {
    // This is how we autostart on iPhone
    if ((function isiPhone(){
      return (
        (navigator.platform.indexOf("iPhone") != -1) ||
        (navigator.platform.indexOf("iPod") != -1) ||
        (navigator.platform.indexOf("iPad") != -1)
      );
    })()) {
      //window.location = "http://tv.bcctv.org/" + channelStream + "/playlist.m3u8"
    }
  }
  setTimeout(function() {jwplayer(bcctv.data.element).play(true);}, 1000);
}

bcctv.player.android = function (element, sources) {
  jwplayer(element).setup({
    file: sources[1].file,
    type: "mp4",
    primary: "html5",
    image: bcctv.player.getImage(),
    logo: bcctv.player.getLogo(),
    playlist: (bcctv.data.playlist ? bcctv.data.playlist : null),
    autostart: "true"
  });
}

bcctv.player.getLogo = function() {
  var logo = {
/*
      file: 'http://www.bcctv.org/sites/bcctv.org/files/bcctv-bug.png',
      link: 'http://www.bcctv.org/',
      position: 'bottom-right',
      margin: 15,
      hide: true
*/
  };
  return logo;
}

bcctv.player.getChannels = function() {
  var channels = [
    {name:"BCCTV prime", host:"tv", app:"origin", stream:"ch1", target:"origin/ch1", image:"snapshot-ch1.jpg"},
    {name:"BCCTV interpret", host:"tv", app:"origin", stream:"ch2", target:"origin/ch2", image:"snapshot-ch2.jpg"},
    {name:"BCCTV go", host:"tv", app:"live", stream:"bcctv-go.stream", target:"live/bcctv-go.stream", image:"snapshot.jpg"},
    {name:"BCCTV venue", host:"tv-columbia", app:"live", stream:"ch3", target:"live/ch3", image:"snapshot.jpg"}
  ];
  bcctv.data.channels = channels;
  return channels;
}

bcctv.player.getSources = function() {
  var channels = bcctv.player.getChannels();
  var index = bcctv.data.channel - 1;
  var streamUrls = bcctv.player.getStreamUrls(channels[index]);
  if (bcctv.data.isLocal) {
    var localStreamChannel = channels[index];
    localStreamChannel.host = "tv-columbia";
    localStreamChannel.app = "live";
    if (localStreamChannel.stream == "bcctv-go.stream")
    // mpegts.stream is fed from SCServer directly to outbound server
      localStreamChannel.app = "liveedge";
    else if (localStreamChannel.stream == "ch1")
    {
    // ch1 is fed from studioB directly to outbound server
      localStreamChannel.app = "liveedge";
      localStreamChannel.stream = "ch1.stream";
    }
    streamUrls = bcctv.player.getStreamUrls(localStreamChannel);
  }

  var sources = [];
  sources.push({
      file: streamUrls[0],
      label: 'rtmp',
      rtmp: { bufferlength: 0.1 },
      image: bcctv.player.getImage()
    });
  sources.push({
      file: streamUrls[1],
      label: 'hls',
      default: true,
      image: bcctv.player.getImage()
    });
  bcctv.data.sources = sources;
  return sources;
}

bcctv.player.getStreamUrls = function(data) {
  var urls = [
    "rtmp://" + data.host + ".bcctv.org/" + data.app + "/" + data.stream,
    "http://" + data.host + ".bcctv.org/" + data.app + "/" + data.stream + "/playlist.m3u8"
  ];
  return urls;
}

bcctv.player.getImage = function() {
  var channels = bcctv.player.getChannels();
  var index = bcctv.data.channel - 1;
  var image = channels[index].image;
  image = 'http://www.bcctv.org/ajax/' + image;
  return image;
}

bcctv.player.reset = function() {
  jwplayer(bcctv.data.element).load({ file:bcctv.player.getSources(), image:bcctv.player.getImage() });
}

bcctv.player.retry = function() {
  if (jwplayer(bcctv.data.element).getState() != "PLAYING")
    bcctv.player.reset();
}

bcctv.player.getStop = function() {
  // TO-DO: Reset timers when stop is pressed
}

//bcctv.player.loadJs("http://ajax.googleapis.com/ajax/libs/jqueryui/2.1.3/jquery.min.js", function() { bcctv.jq=jQuery; jQuery.noConflict(); $=jQuery; });

bcctv.player.getIp = function() {
  $.getJSON("https://api.ipify.org?format=jsonp&callback=?", function(json) {
    bcctv.data.clientIp = json.ip;
    var isLocal = ($.inArray(bcctv.data.clientIp,
      [
        "70.88.137.97" ,
        "65.199.40.226"
      ]) >= 0);
    if (bcctv.data.isLocal != isLocal) {
      bcctv.data.isLocal = isLocal;
      bcctv.player.reset();
    }
  });
}

//
// bcctv.ui
//

bcctv.ui.init = function(options) {
  if (!options)
    options = {};
  bcctv.ui.getData(options);
  // refresh data every 15 seconds
  var scheduleRefresh = setInterval(function() { bcctv.ui.update(options); },15000);
}

bcctv.ui.update = function(options) {
  bcctv.ui.getData(options);
  bcctv.ui.showChannels();
}

//Default function for showPaneData
showPaneData = function(data) { bcctv.data.update = data; /*console.log(JSON.stringify(data));*/}

bcctv.ui.getData = function(options) {
  var stamp = new Date().getTime();
  var refreshCallback = options.callback;
  $.ajax({
    url:'http://cdn.bcctv.org/ajax/getUpdate.php',
    dataType:'jsonp',
    jsonp:'callback',
    jsonpCallback:refreshCallback
  });
  if (typeof ga == 'function')
    ga('send', 'pageview');
  bcctv.player.getIp();
}

bcctv.ui.getActiveChannels = function() {
  if (bcctv.data.update && bcctv.data.update.now) {
    var now = $('<div>').html(bcctv.data.update.now);
    var channels = now.find('.views-field-field-channels-value .field-content');
    if (channels.length) {
      var channelText = [];
      var channelsEach = channels.find('.field-item');
      if (channelsEach.length) {
        channelsEach.each(function() {
          channelText.push($(this).text());
        });
      } else {
        channelText.push(channels.text());
      }
      return channelText;
    }
  }
}

bcctv.ui.showChannels = function() {
  var sourceChannels = bcctv.player.getChannels();
  var activeChannels = bcctv.ui.getActiveChannels();
  var channels = [];
  if (! activeChannels) // are there any active channels for this program?
  {
    $('select#channels').remove();
    return;
  }
  for (var i=0; i<activeChannels.length; i++) {
    var channel = activeChannels[i];
    for (var j=0; j<sourceChannels.length; j++) {
      if (sourceChannels[j].name == channel)
        channels.push(sourceChannels[j]);
    }
  }
  if (! channels.length) // do we have matches of available channels?
    return;
  if (bcctv.data.oldChannels && bcctv.data.oldChannels == channels)  // is the channel setting the same as before?
    return;

  bcctv.data.oldChannels = channels;
  $('select#channels').remove();
  var selector = '.'+bcctv.data.element+'-container';
  $(selector)
    .append('<select name="channels" id="channels"></select>')
    .css('border','2px solid black').children('select')
    .css('width','100%')
    .css('height','8%');
  for (var i=1; i<=channels.length; i++) {
    var index = i-1;
    $(selector + ' select')
      .append('<option value="'+ i +'">[Channel '+ i +']: '+ channels[index].name +'</option>');
  }
  $(selector + ' select').val(bcctv.data.channel).change();
  $(selector + ' select').change(function() {
    var channel = $(this).find('option:selected').val();
    bcctv.data.channel = channel;
    //jwplayer(bcctv.data.element).load([{ sources:bcctv.player.getSources() }]);
    jwplayer(bcctv.data.element).load( bcctv.player.getSources() );
    jwplayer(bcctv.data.element).play(true);
  });
}

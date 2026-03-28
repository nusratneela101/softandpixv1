
// 2. This code loads the IFrame Player API code asynchronously.
var tag = document.createElement('script');

tag.src = "https://www.youtube.com/iframe_api";
var firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

// 3. This function creates an <iframe> (and YouTube player)
//    after the API code downloads.
var player2;
var player3;
function onYouTubeIframeAPIReady() {
    player2 = new YT.Player('player2', {
        width: '100%',
        height: '100%',
        videoId: 'Uhnmjj32QBU',
        playerVars: {
            'autoplay': 1,
            'playsinline': 1,
            'loop': 1,
            'rel': 0,
            'showinfo': 1,
            'mute': 1,
            'playlist': 'Uhnmjj32QBU',
            'frameborder': 0,
            'controls': 0,
            ' modestbranding': 1
        },
        events: {
            'onReady': onPlayerReadyy
        }
    });
    player3 = new YT.Player('player3', {
        width: '100%',
        videoId: '-OTwyGZ8bsA',
        playerVars: {
            'autoplay': 1,
            'playsinline': 1,
            'loop': 1,
            'rel': 0,
            'showinfo': 1,
            'mute': 1,
            'playlist': '-OTwyGZ8bsA',
            'frameborder': 0,
            'controls': 0,
            ' modestbranding': 1
        },
        events: {
            'onReady': onPlayerReadyyy
        }
    });

}

// 4. The API will call this function when the video player is ready.
function onPlayerReadyy(event) {
    event.target.mute();
    event.target.playVideo();
}
// 4. The API will call this function when the video player is ready.
function onPlayerReadyyy(event) {
    event.target.mute();
    event.target.playVideo();
}



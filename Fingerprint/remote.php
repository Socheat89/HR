<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Remote Desktop with Access Code</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.5.1/socket.io.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
    #remoteVideo, #localVideo { width: 100%; max-width: 800px; border: 1px solid #ccc; }
    #controls { margin-top: 10px; }
    button { padding: 10px; margin: 5px; }
    #status { color: green; }
    input { padding: 8px; width: 200px; }
  </style>
</head>
<body>
  <h1>Remote Desktop with Access Code</h1>
  <div>
    <h2>Remote Screen</h2>
    <video id="remoteVideo" autoplay playsinline></video>
  </div>
  <div>
    <h2>Local Preview (Optional)</h2>
    <video id="localVideo" autoplay playsinline muted></video>
  </div>
  <div id="controls">
    <button id="startButton">Start Screen Share</button>
    <button id="connectButton" disabled>Connect to Remote</button>
    <input id="accessCode" type="text" placeholder="Enter Access Code">
    <p id="status">Status: Waiting...</p>
  </div>

  <script>
    // Connect to signaling server
    const socket = io('https://yourdomain.com:3000'); // Replace with your domain and port

    // WebRTC configuration
    const configuration = {
      iceServers: [
        { urls: 'stun:stun.l.google.com:19302' }
      ]
    };

    let peerConnection;
    let dataChannel;
    const remoteVideo = document.getElementById('remoteVideo');
    const localVideo = document.getElementById('localVideo');
    const startButton = document.getElementById('startButton');
    const connectButton = document.getElementById('connectButton');
    const accessCodeInput = document.getElementById('accessCode');
    const status = document.getElementById('status');

    // Generate unique access code
    const accessCode = Math.random().toString(36).substring(2, 10);
    status.textContent = `Status: Your Access Code is ${accessCode}`;
    socket.emit('register', accessCode);

    // Start screen sharing
    startButton.onclick = async () => {
      try {
        const stream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        localVideo.srcObject = stream;

        peerConnection = new RTCPeerConnection(configuration);
        stream.getTracks().forEach(track => peerConnection.addTrack(track, stream));

        dataChannel = peerConnection.createDataChannel('control');
        dataChannel.onopen = () => status.textContent = 'Status: Data channel open';
        dataChannel.onmessage = (event) => {
          const { type, data } = JSON.parse(event.data);
          handleRemoteInput(type, data);
        };

        peerConnection.onicecandidate = (event) => {
          if (event.candidate) {
            socket.emit('ice-candidate', { accessCode, candidate: event.candidate });
          }
        };

        peerConnection.ontrack = (event) => {
          remoteVideo.srcObject = event.streams[0];
          status.textContent = 'Status: Connected to remote screen';
        };

        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);
        socket.emit('offer', { accessCode, offer });
        connectButton.disabled = false;
      } catch (error) {
        status.textContent = `Status: Error - ${error.message}`;
      }
    };

    // Connect to remote peer
    connectButton.onclick = async () => {
      const remoteAccessCode = accessCodeInput.value;
      if (!remoteAccessCode) {
        status.textContent = 'Status: Enter a valid Access Code';
        return;
      }

      try {
        peerConnection = new RTCPeerConnection(configuration);
        peerConnection.ondatachannel = (event) => {
          dataChannel = event.channel;
          dataChannel.onopen = () => status.textContent = 'Status: Data channel open';
          dataChannel.onmessage = (event) => {
            const { type, data } = JSON.parse(event.data);
            handleRemoteInput(type, data);
          };
        };

        peerConnection.onicecandidate = (event) => {
          if (event.candidate) {
            socket.emit('ice-candidate', { accessCode: remoteAccessCode, candidate: event.candidate });
          }
        };

        socket.emit('request-offer', remoteAccessCode);
      } catch (error) {
        status.textContent = `Status: Error - ${error.message}`;
      }
    };

    // Handle signaling events
    socket.on('offer', async (offer) => {
      if (!peerConnection) return;
      await peerConnection.setRemoteDescription(offer);
      const answer = await peerConnection.createAnswer();
      await peerConnection.setLocalDescription(answer);
      socket.emit('answer', { accessCode: accessCodeInput.value, answer });
    });

    socket.on('answer', async (answer) => {
      if (!peerConnection) return;
      await peerConnection.setRemoteDescription(answer);
    });

    socket.on('ice-candidate', async (candidate) => {
      if (!peerConnection) return;
      await peerConnection.addIceCandidate(candidate);
    });

    // Handle remote input
    function handleRemoteInput(type, data) {
      if (type === 'mouse') {
        console.log('Remote mouse event:', data);
      } else if (type === 'keyboard') {
        console.log('Remote keyboard event:', data);
      }
    }

    // Send mouse/keyboard input
    document.addEventListener('mousemove', (e) => {
      if (dataChannel?.readyState === 'open') {
        const data = { x: e.clientX, y: e.clientY };
        dataChannel.send(JSON.stringify({ type: 'mouse', data }));
      }
    });

    document.addEventListener('keydown', (e) => {
      if (dataChannel?.readyState === 'open') {
        const data = { key: e.key, code: e.code };
        dataChannel.send(JSON.stringify({ type: 'keyboard', data }));
      }
    });
  </script>
</body>
</html>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persistent Image Gallery</title>
    <style>
        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: #f4f4f4;
            margin: 20px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            text-align: center;
            padding: 30px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        #file-input { display: none; }
        .upload-label {
            display: inline-block; padding: 12px 25px; background-color: #007bff;
            color: white; border-radius: 5px; cursor: pointer;
            font-size: 16px; margin-bottom: 20px;
        }
        #status { margin-top: 15px; font-weight: bold; min-height: 20px; }
        #image-gallery {
            margin-top: 20px; display: flex; flex-wrap: wrap;
            gap: 15px; justify-content: center; min-height: 150px;
            padding: 10px;
        }
        .image-container {
            position: relative; width: 150px; height: 150px;
            border-radius: 8px; overflow: hidden; border: 2px solid #ddd;
            background-color: #eee;
        }
        .gallery-image {
            width: 100%; height: 100%; object-fit: cover;
        }
        .copy-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); color: white;
            display: flex; justify-content: center; align-items: center;
            font-size: 14px; font-weight: bold; opacity: 0;
            transition: opacity 0.3s; cursor: pointer;
        }
        .image-container:hover .copy-overlay { opacity: 1; }
        #copy-status-popup {
            position: fixed; bottom: 20px; left: 50%;
            transform: translateX(-50%); background-color: #28a745;
            color: white; padding: 10px 25px; border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); display: none; z-index: 1000;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Persistent Image Gallery</h1>
        <p>រូបភាពនឹងនៅដដែល ទោះបីជាអ្នកផ្ទុកទំព័រឡើងវិញក៏ដោយ។</p>

        <label for="file-input" class="upload-label">ជ្រើសរើសរូបភាព (អាចច្រើន)</label>
        <input type="file" id="file-input" accept="image/*" multiple>

        <p id="status"></p>
        
        <div id="image-gallery"></div>
    </div>
    
    <div id="copy-status-popup">បានចម្លង Link ហើយ!</div>

    <script>
        const fileInput = document.getElementById('file-input');
        const statusEl = document.getElementById('status');
        const imageGallery = document.getElementById('image-gallery');
        const copyStatusPopup = document.getElementById('copy-status-popup');

        // មុខងារសម្រាប់គ្រប់គ្រងការឆ្លើយតបពី Fetch
        function handleResponse(response) {
            const contentType = response.headers.get("content-type");
            if (response.ok && contentType && contentType.indexOf("application/json") !== -1) {
                return response.json();
            }
            // ប្រសិនបើមិនមែនជា JSON ឬមានកំហុស សូមអានជា Text រួចបង្ហាញ Error
            return response.text().then(text => {
                // កាត់ Tag HTML ចេញដើម្បីឱ្យសារ Error ខ្លី និងច្បាស់លាស់ជាងមុន
                const cleanText = text.replace(/<[^>]*>/g, '').substring(0, 200);
                throw new Error(`Server did not return valid JSON. Response: ${cleanText}...`);
            });
        }

        // មុខងារសម្រាប់ទាញយករូបភាពដែលមានស្រាប់
        function loadExistingImages() {
            statusEl.textContent = "កំពុងទាញយករូបភាព...";
            statusEl.style.color = 'inherit';

            fetch('get_images.php')
                .then(handleResponse)
                .then(data => {
                    if (data.success && data.images) {
                        imageGallery.innerHTML = '';
                        if (data.images.length > 0) {
                            statusEl.textContent = `រកឃើញ ${data.images.length} រូបភាព។`;
                            data.images.forEach(imageUrl => displayImage(imageUrl));
                        } else {
                            statusEl.textContent = "មិនទាន់មានរូបភាពដែលបាន Upload នៅឡើយ។";
                        }
                    } else {
                        throw new Error(data.error || 'Unknown error from server.');
                    }
                })
                .catch(error => {
                    console.error("Error loading images:", error);
                    statusEl.textContent = `មិនអាចទាញយករូបភាពបានទេ៖ ${error.message}`;
                    statusEl.style.color = 'red';
                });
        }

        // មុខងារសម្រាប់ Upload ឯកសារ
        function uploadFile(file) {
            return new Promise((resolve) => {
                const formData = new FormData();
                formData.append('image', file);

                fetch('upload.php', { method: 'POST', body: formData })
                    .then(handleResponse)
                    .then(data => {
                        if (data.success && data.url) {
                            displayImage(data.url, true);
                            resolve({ success: true });
                        } else {
                            throw new Error(data.error || 'Unknown upload error');
                        }
                    })
                    .catch(error => {
                        console.error(`Error uploading ${file.name}:`, error.message);
                        statusEl.innerHTML += `<br><span style="color:red;">បរាជ័យក្នុងការ Upload "${file.name}"</span>`;
                        resolve({ success: false }); // កែសម្រួល Promise ដើម្បីឱ្យ Promise.all បន្តដំណើរការ
                    });
            });
        }
        
        // មុខងារសម្រាប់បង្ហាញរូបភាព
        function displayImage(imageUrl, prepend = false) {
            const container = document.createElement('div');
            container.className = 'image-container';

            const img = document.createElement('img');
            img.src = imageUrl;
            img.className = 'gallery-image';

            const overlay = document.createElement('div');
            overlay.className = 'copy-overlay';
            overlay.textContent = 'ចម្លង Link';

            container.appendChild(img);
            container.appendChild(overlay);

            container.addEventListener('click', () => {
                navigator.clipboard.writeText(imageUrl).then(showCopyPopup);
            });

            if (prepend) {
                imageGallery.prepend(container);
            } else {
                imageGallery.appendChild(container);
            }
        }

        function showCopyPopup() {
            copyStatusPopup.style.display = 'block';
            setTimeout(() => {
                copyStatusPopup.style.display = 'none';
            }, 2000);
        }
        
        // Event Listener សម្រាប់ការជ្រើសរើសឯកសារ
        fileInput.addEventListener('change', function(event) {
            const files = event.target.files;
            if (files.length === 0) return;

            statusEl.textContent = `កំពុងរៀបចំ Upload ${files.length} រូបភាព...`;
            
            const uploadPromises = Array.from(files).map(uploadFile);

            Promise.all(uploadPromises).then((results) => {
                const successCount = results.filter(r => r.success).length;
                statusEl.textContent = `ការ Upload បានបញ្ចប់ (${successCount}/${files.length} ជោគជ័យ)`;
            });
        });

        // ចាប់ផ្ដើមទាញយករូបភាពនៅពេលទំព័រផ្ទុករួចរាល់
        document.addEventListener('DOMContentLoaded', loadExistingImages);

    </script>

</body>
</html>
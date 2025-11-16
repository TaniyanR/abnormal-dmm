<?php
/**
 * video-list.php
 * 
 * HTML template for displaying a list of videos.
 * This is a starter template that can be customized for frontend display.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Store - Browse Videos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .video-card {
            background: white;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .video-card img {
            width: 100%;
            height: auto;
            border-radius: 3px;
        }
        .video-title {
            font-weight: bold;
            margin: 10px 0;
        }
        .pagination {
            text-align: center;
            padding: 20px;
        }
        .pagination button {
            padding: 10px 20px;
            margin: 0 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Video Store</h1>
        <p>Browse our collection of videos</p>
    </div>

    <div class="video-grid" id="videoGrid">
        <!-- Videos will be loaded here via JavaScript -->
    </div>

    <div class="pagination">
        <button id="prevBtn" onclick="loadPrevPage()">Previous</button>
        <span id="pageInfo"></span>
        <button id="nextBtn" onclick="loadNextPage()">Next</button>
    </div>

    <script>
        let currentPage = 0;
        const itemsPerPage = 20;

        async function loadVideos(offset = 0) {
            try {
                // Note: Using /api/items endpoint which is the existing API
                // This scaffolding integrates with the existing application
                const response = await fetch(`/api/items?limit=${itemsPerPage}&offset=${offset}`);
                const data = await response.json();
                
                if (data.success) {
                    displayVideos(data.data.items);
                    updatePagination(data.data);
                }
            } catch (error) {
                console.error('Failed to load videos:', error);
            }
        }

        function displayVideos(videos) {
            const grid = document.getElementById('videoGrid');
            grid.innerHTML = '';
            
            videos.forEach(video => {
                const card = document.createElement('div');
                card.className = 'video-card';
                
                // Create elements to prevent XSS
                if (video.imageURL) {
                    const img = document.createElement('img');
                    img.src = video.imageURL;
                    img.alt = video.title || 'Video thumbnail';
                    card.appendChild(img);
                }
                
                const titleDiv = document.createElement('div');
                titleDiv.className = 'video-title';
                titleDiv.textContent = video.title || 'Untitled';
                card.appendChild(titleDiv);
                
                const dateDiv = document.createElement('div');
                dateDiv.className = 'video-date';
                dateDiv.textContent = video.date || '';
                card.appendChild(dateDiv);
                
                grid.appendChild(card);
            });
        }

        function updatePagination(data) {
            document.getElementById('pageInfo').textContent = 
                `Showing ${data.offset + 1} - ${Math.min(data.offset + data.limit, data.total)} of ${data.total}`;
            
            document.getElementById('prevBtn').disabled = data.offset === 0;
            document.getElementById('nextBtn').disabled = (data.offset + data.limit) >= data.total;
        }

        function loadPrevPage() {
            if (currentPage > 0) {
                currentPage--;
                loadVideos(currentPage * itemsPerPage);
            }
        }

        function loadNextPage() {
            const nextBtn = document.getElementById('nextBtn');
            if (!nextBtn.disabled) {
                currentPage++;
                loadVideos(currentPage * itemsPerPage);
            }
        }

        // Load initial videos
        loadVideos();
    </script>
</body>
</html>

</div> <!-- End of content-wrapper -->
</div> <!-- End of row -->
</div> <!-- End of container-fluid -->

<?php
// Check if user is logged in and not on take-exam.php page
$current_page = basename($_SERVER['PHP_SELF']);
$is_exam_page = ($current_page === 'take-exam.php');

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && !$is_exam_page && !isset($no_floating_chat)):
?>
    <!-- Floating Chat Bot -->
    <link rel="stylesheet" href="<?php echo isset($base_url) ? $base_url : ""; ?>/assets/css/floating-chat.css">

    <div class="floating-chat-container">
        <div class="chat-bubble" id="chatBubble">
            <i class="fas fa-comment-dots"></i>
        </div>
    </div>

    <div class="chat-popup" id="chatPopup">
        <div class="chat-header">
            <div>
                <i class="fas fa-robot"></i>
                <h6>AI Assistant</h6>
            </div>
            <button class="close-chat" id="closeChat">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <!-- Messages will be loaded here -->
        </div>
        <div class="chat-input-container">
            <input type="text" class="chat-input" id="chatInput" placeholder="Ask a question...">
            <button class="send-button" id="sendMessage">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatBubble = document.getElementById('chatBubble');
            const chatPopup = document.getElementById('chatPopup');
            const closeChat = document.getElementById('closeChat');
            const chatInput = document.getElementById('chatInput');
            const sendMessage = document.getElementById('sendMessage');
            const chatMessages = document.getElementById('chatMessages');

            // Toggle chat popup
            chatBubble.addEventListener('click', function() {
                chatPopup.style.display = 'flex';
                loadChatHistory();
            });

            // Close chat popup
            closeChat.addEventListener('click', function() {
                chatPopup.style.display = 'none';
            });

            // Send message on button click
            sendMessage.addEventListener('click', sendUserMessage);

            // Send message on Enter key
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendUserMessage();
                }
            });

            // Function to send user message
            function sendUserMessage() {
                const message = chatInput.value.trim();
                if (message === '') return;

                // Add user message to chat
                addMessageToChat('user', message);
                chatInput.value = '';

                // Send to server
                fetch('<?php echo isset($base_url) ? $base_url : ""; ?>/ai-chat/floating-chat-api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            message: message
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.response) {
                            addMessageToChat('ai', data.response);
                        } else if (data.error) {
                            addMessageToChat('ai', 'Sorry, an error occurred: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        addMessageToChat('ai', 'Sorry, an error occurred while processing your request.');
                    });
            }

            // Function to add message to chat
            function addMessageToChat(sender, message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = sender === 'user' ? 'user-message' : 'ai-message';
                messageDiv.className += ' clearfix';
                messageDiv.textContent = message;

                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Function to load chat history
            function loadChatHistory() {
                fetch('<?php echo isset($base_url) ? $base_url : ""; ?>/ai-chat/get-chat-history.php')
                    .then(response => response.json())
                    .then(data => {
                        chatMessages.innerHTML = '';
                        if (data.history && data.history.length > 0) {
                            data.history.forEach(chat => {
                                addMessageToChat('user', chat.message);
                                addMessageToChat('ai', chat.response);
                            });
                        } else {
                            addMessageToChat('ai', 'Hello! How can I help you with the Smart Exam Portal today?');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        chatMessages.innerHTML = '';
                        addMessageToChat('ai', 'Hello! How can I help you with the Smart Exam Portal today?');
                    });
            }
        });
    </script>
<?php endif; ?>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script src="<?php echo isset($base_url) ? $base_url : ""; ?>/assets/js/script.js"></script>
<!-- Modal enhancement scripts -->
<script src="<?php echo isset($base_url) ? $base_url : ""; ?>/assets/js/modal-enhancements.js"></script>
<!-- Responsive Sidebar JS -->
<script src="<?php echo isset($base_url) ? $base_url : ""; ?>/assets/js/sidebar.js"></script>
<?php if (isset($extra_js)): ?>
    <?php echo $extra_js; ?>
<?php endif; ?>
</body>

</html>
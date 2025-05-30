#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <WebServer.h>
#include "esp_camera.h"
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// WiFi credentials
const char* ssid = "Test";
const char* password = "17011994";

// Server info
String serverName = "172.20.10.11"; // ⚠️ XÓA khoảng trắng phía trước
String serverPath = "/doan1/upload_image_ra.php";
const int serverPort = 80;

WiFiClient client;
WebServer server(80);

// Camera pins for AI Thinker module
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

void startCustomCameraStream();

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0); // Tắt brownout
  Serial.begin(115200);

  WiFi.begin(ssid, password);
  Serial.print("🔌 Đang kết nối WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n✅ Kết nối WiFi thành công");
  Serial.print("📡 ESP32 IP: ");
  Serial.println(WiFi.localIP());

  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;

  if (psramFound()) {
    config.frame_size = FRAMESIZE_SVGA;
    config.jpeg_quality = 10;
    config.fb_count = 2;
  } else {
    config.frame_size = FRAMESIZE_CIF;
    config.jpeg_quality = 12;
    config.fb_count = 1;
  }

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("❌ Lỗi khởi động camera!");
    delay(1000);
    ESP.restart();
  }

  startCustomCameraStream();
}

void loop() {
  server.handleClient();

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ Mất kết nối WiFi. Đang thử lại...");
    WiFi.reconnect();
    delay(1000);
    return;
  }

  sendPhoto();
  delay(3000); // delay giữa các lần gửi
}

String sendPhoto() {
  camera_fb_t * fb = esp_camera_fb_get();
  if (!fb) {
    Serial.println("❌ Không chụp được ảnh");
    return "Capture failed";
  }

  Serial.println("📤 Gửi ảnh đến server...");

  if (!client.connect(serverName.c_str(), serverPort)) {
    Serial.println("❌ Kết nối đến server thất bại");
    esp_camera_fb_return(fb);
    return "Connection failed";
  }

  String head = "--RandomNerdTutorials\r\n"
                "Content-Disposition: form-data; name=\"imageFile\"; filename=\"esp32-cam.jpg\"\r\n"
                "Content-Type: image/jpeg\r\n\r\n";
  String tail = "\r\n--RandomNerdTutorials--\r\n";

  uint32_t totalLen = head.length() + fb->len + tail.length();

  client.println("POST " + serverPath + " HTTP/1.1");
  client.println("Host: " + serverName);
  client.println("Content-Length: " + String(totalLen));
  client.println("Content-Type: multipart/form-data; boundary=RandomNerdTutorials");
  client.println();
  client.print(head);

  size_t chunkSize = 1024;
  for (size_t i = 0; i < fb->len; i += chunkSize) {
    size_t len = (i + chunkSize < fb->len) ? chunkSize : fb->len - i;
    client.write(fb->buf + i, len);
  }

  client.print(tail);
  esp_camera_fb_return(fb);

  String response = "";
  unsigned long timeout = millis();
  while (client.connected() && millis() - timeout < 5000) {
    while (client.available()) {
      char c = client.read();
      response += c;
    }
  }
  client.stop();

  Serial.println("📥 Phản hồi server:");
  Serial.println(response);
  return response;
}

void startCustomCameraStream() {
  server.on("/", HTTP_GET, []() {
    server.send(200, "text/html", "<h1>ESP32-CAM Streaming</h1><img src='/stream'>");
  });

  server.on("/stream", HTTP_GET, []() {
    WiFiClient client = server.client();

    String response = "HTTP/1.1 200 OK\r\n"
                      "Content-Type: multipart/x-mixed-replace; boundary=frame\r\n\r\n";
    server.sendContent(response);

    while (client.connected()) {
      camera_fb_t * fb = esp_camera_fb_get();
      if (!fb) continue;

      response = "--frame\r\nContent-Type: image/jpeg\r\n\r\n";
      server.sendContent(response);
      client.write(fb->buf, fb->len);
      server.sendContent("\r\n");
      esp_camera_fb_return(fb);
      delay(100);
    }
  });

  server.begin();
  Serial.println("📺 Stream đang chạy tại:");
  Serial.print("🔗 http://");
  Serial.print(WiFi.localIP());
  Serial.println("/stream");
}

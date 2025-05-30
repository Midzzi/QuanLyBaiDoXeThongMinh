#include <SPI.h>
#include <MFRC522.h>
#include <Servo.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// RFID
#define SS_PIN_1 49  // Cổng ra
#define RST_PIN_1 7
#define SS_PIN_2 53  // Cổng vào
#define RST_PIN_2 6

// Servo
#define SERVO_IN_PIN 9
#define SERVO_OUT_PIN 8

// Buzzer
#define BUZZER_PIN 5

// Cảm biến siêu âm
#define TRIG1 22
#define ECHO1 23
#define TRIG2 24
#define ECHO2 25
#define TRIG3 26
#define ECHO3 27

// LED: đỏ, xanh, đỏ, xanh, đỏ, xanh (bãi 1, 2, 3)
int ledPins[] = {3, 4, 28, 29, 30, 31};

// LCD
LiquidCrystal_I2C lcd(0x27, 20, 4);

// RFID
MFRC522 mfrc522_1(SS_PIN_1, RST_PIN_1);  // Cổng ra
MFRC522 mfrc522_2(SS_PIN_2, RST_PIN_2);  // Cổng vào

// Servo
Servo servoIn, servoOut;

bool cardScanned = false;
unsigned long lastCardScanTime = 0;

// Trạng thái trước đó của bãi đỗ
bool lastSpot1 = false, lastSpot2 = false, lastSpot3 = false;

// Danh sách thẻ hợp lệ
const byte validUIDs[][4] = {
  {0xD9, 0xD1, 0x91, 0x02},
  {0x96, 0xA1, 0x43, 0x05},
  {0xAD, 0x7D, 0xB9, 0x02},
  {0x35, 0xC7, 0xB5, 0x02},
  {0x4E, 0x57, 0xFC, 0x00}
};
const int NUM_VALID_UIDS = sizeof(validUIDs) / sizeof(validUIDs[0]);

void setup() {
  Serial.begin(9600);
  SPI.begin();

  // RFID
  pinMode(SS_PIN_1, OUTPUT);
  pinMode(SS_PIN_2, OUTPUT);
  digitalWrite(SS_PIN_1, HIGH);
  digitalWrite(SS_PIN_2, HIGH);
  mfrc522_1.PCD_Init();
  mfrc522_2.PCD_Init();

  // Servo
  servoIn.attach(SERVO_IN_PIN);
  servoOut.attach(SERVO_OUT_PIN);
  servoIn.write(0);
  servoOut.write(0);

  // Cảm biến
  pinMode(TRIG1, OUTPUT); pinMode(ECHO1, INPUT);
  pinMode(TRIG2, OUTPUT); pinMode(ECHO2, INPUT);
  pinMode(TRIG3, OUTPUT); pinMode(ECHO3, INPUT);

  // Buzzer
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  // LED
  for (int i = 0; i < 6; i++) {
    pinMode(ledPins[i], OUTPUT);
    digitalWrite(ledPins[i], LOW);
  }

  // LCD
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("He thong khoi dong");
  delay(1000);
  lcd.clear();
}

long readDistanceCM(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW); delayMicroseconds(2);
  digitalWrite(trigPin, HIGH); delayMicroseconds(10);
  digitalWrite(trigPin, LOW);
  long duration = pulseIn(echoPin, HIGH, 30000);
  if (duration == 0) return 999;
  return duration * 0.034 / 2;
}

void beepBuzzer(int duration = 300) {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(duration);
  digitalWrite(BUZZER_PIN, LOW);
}

bool isValidCard(MFRC522 &reader) {
  for (int i = 0; i < NUM_VALID_UIDS; i++) {
    bool match = true;
    for (int j = 0; j < 4; j++) {
      if (reader.uid.uidByte[j] != validUIDs[i][j]) {
        match = false;
        break;
      }
    }
    if (match) return true;
  }
  return false;
}

void handleCard(MFRC522 &reader, int ssPin, bool isEntry) {
  if (millis() - lastCardScanTime < 3000) return;

  digitalWrite(SS_PIN_1, HIGH);
  digitalWrite(SS_PIN_2, HIGH);
  digitalWrite(ssPin, LOW);

  if (reader.PICC_IsNewCardPresent() && reader.PICC_ReadCardSerial() && !cardScanned) {
    cardScanned = true;
    lastCardScanTime = millis();

    Serial.print("UID cua the: ");
    for (byte i = 0; i < reader.uid.size; i++) {
      Serial.print(reader.uid.uidByte[i] < 0x10 ? " 0" : " ");
      Serial.print(reader.uid.uidByte[i], HEX);
    }
    Serial.println();

    if (isValidCard(reader)) {
      beepBuzzer(300);  // ✅ Buzzer kêu khi hợp lệ

      // Mở cổng
      if (isEntry) {
        servoIn.write(90);
        delay(2000);
        servoIn.write(0);
      } else {
        servoOut.write(90);
        delay(2000);
        servoOut.write(0);
      }
    } else {
      Serial.println("❌ The khong hop le!");
      beepBuzzer(1000);  // ❌ Kêu dài nếu thẻ không hợp lệ
    }

    reader.PICC_HaltA();
  }

  digitalWrite(ssPin, HIGH);
}

void updateLEDStatus(bool s1, bool s2, bool s3) {
  digitalWrite(ledPins[5], s1 ? HIGH : LOW);  // Bãi 1 xanh
  digitalWrite(ledPins[4], s1 ? LOW : HIGH);  // Bãi 1 đỏ
  digitalWrite(ledPins[3], s2 ? HIGH : LOW);  // Bãi 2 xanh
  digitalWrite(ledPins[2], s2 ? LOW : HIGH);  // Bãi 2 đỏ
  digitalWrite(ledPins[0], s3 ? HIGH : LOW);  // Bãi 3 xanh
  digitalWrite(ledPins[1], s3 ? LOW : HIGH);  // Bãi 3 đỏ
}

void displayParkingStatus(bool s1, bool s2, bool s3) {
  if (s1 != lastSpot1 || s2 != lastSpot2 || s3 != lastSpot3) {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Tinh trang cho:");

    lcd.setCursor(0, 1); lcd.print("1: "); lcd.print(s1 ? "Con cho " : "Het cho ");
    lcd.setCursor(0, 2); lcd.print("2: "); lcd.print(s2 ? "Con cho " : "Het cho ");
    lcd.setCursor(0, 3); lcd.print("3: "); lcd.print(s3 ? "Con cho " : "Het cho ");

    lastSpot1 = s1;
    lastSpot2 = s2;
    lastSpot3 = s3;

    updateLEDStatus(s1, s2, s3);
  }
}

void loop() {
  long d1 = readDistanceCM(TRIG1, ECHO1);
  long d2 = readDistanceCM(TRIG2, ECHO2);
  long d3 = readDistanceCM(TRIG3, ECHO3);

  bool spot1 = d1 > 10;
  bool spot2 = d2 > 10;
  bool spot3 = d3 > 10;

  displayParkingStatus(spot1, spot2, spot3);

  handleCard(mfrc522_2, SS_PIN_2, true);   // Cổng vào
  handleCard(mfrc522_1, SS_PIN_1, false);  // Cổng ra

  if (cardScanned &&
      !mfrc522_1.PICC_IsNewCardPresent() &&
      !mfrc522_2.PICC_IsNewCardPresent()) {
    cardScanned = false;
  }

  delay(300);
}

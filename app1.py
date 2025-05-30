import cv2
import easyocr
import os
import re
import numpy as np
import time
import mysql.connector
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

reader = easyocr.Reader(['en', 'vi'])

def connect_to_db():
    try:
        db = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="baidoxe_tm",
            port=3307
        )
        return db
    except mysql.connector.Error as err:
        print(f"Lỗi kết nối DB: {err}")
        return None

def preprocess_image(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    adjusted = cv2.convertScaleAbs(gray, alpha=1.5, beta=50)
    blur = cv2.bilateralFilter(adjusted, 11, 17, 17)
    sharpen_kernel = np.array([[-1, -1, -1],
                               [-1,  9, -1],
                               [-1, -1, -1]])
    sharpened = cv2.filter2D(blur, -1, sharpen_kernel)
    return sharpened

def detect_plate(image_path):
    print(f"Đang xử lý: {image_path}")
    if not os.path.exists(image_path):
        print("Ảnh không tồn tại.")
        return

    image = cv2.imread(image_path)
    if image is None:
        print("Không đọc được ảnh.")
        return

    processed = preprocess_image(image)

    try:
        results = reader.readtext(processed, detail=1)
    except Exception as e:
        print(f"Lỗi OCR: {e}")
        return

    if not results:
        print("Không tìm thấy text.")
        os.remove(image_path)
        return

    results = [r for r in results if len(re.sub(r'[^A-Za-z0-9]', '', r[1].strip())) >= 3 and r[2] > 0.5]
    if not results:
        print("Không có text hợp lệ.")
        os.remove(image_path)
        return

    # Ghép biển số
    plate = ' '.join([re.sub(r'[^A-Za-z0-9]', '', r[1].strip()) for r in results])
    print(f"Biển số: {plate}")
    
    # Ghi biển số vào file last_plate1.txt (biển số VÀO)
    last_plate_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "last_plate1.txt")
    with open(last_plate_path, "w", encoding="utf-8") as f:
        f.write(plate)
    print(f"Đã lưu biển số vào: {plate}")

    # Cập nhật database
    db = connect_to_db()
    if db:
        cursor = db.cursor()
        cursor.execute("SELECT COUNT(*) FROM vehicles WHERE bien_so=%s", (plate,))
        (count,) = cursor.fetchone()

        if count:
            cursor.execute("UPDATE vehicles SET vao=NOW() WHERE bien_so=%s", (plate,))
            print("Cập nhật thời gian vào.")
        else:
            cursor.execute("INSERT INTO vehicles (bien_so, vao) VALUES (%s, NOW())", (plate,))
            print("Thêm mới biển số với thời gian vào.")
        db.commit()
        cursor.close()
        db.close()

class ImageHandler(FileSystemEventHandler):
    def on_created(self, event):
        if event.src_path.lower().endswith(('.jpg','.jpeg','.png')):
            time.sleep(1)
            detect_plate(event.src_path)

def monitor():
    folder = r"C:\xampp\htdocs\doan1\anhvao"
    if not os.path.exists(folder):
        print("Thư mục ảnh vào không tồn tại!")
        try:
            os.makedirs(folder, exist_ok=True)
            print(f"Đã tạo thư mục: {folder}")
        except Exception as e:
            print(f"Không thể tạo thư mục: {e}")
            return

    event_handler = ImageHandler()
    observer = Observer()
    observer.schedule(event_handler, folder, recursive=False)
    observer.start()
    print(f"Đang theo dõi thư mục: {folder}")

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
    observer.join()

if __name__ == "__main__":
    monitor()

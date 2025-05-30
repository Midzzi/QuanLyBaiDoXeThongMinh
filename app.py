import cv2
import easyocr
import os
import re
import numpy as np
import time
import mysql.connector
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

# Khởi tạo EasyOCR
reader = easyocr.Reader(['en', 'vi'])

# Kết nối cơ sở dữ liệu MySQL với cổng 3307
def connect_to_db():
    try:
        db = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="baidoxe_tm",
            port=3307  # Thay đổi cổng ở đây
        )
        return db
    except mysql.connector.Error as err:
        print(f"❌ Lỗi kết nối cơ sở dữ liệu: {err}")
        return None

# Tiền xử lý ảnh
def preprocess_image(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    alpha = 1.5
    beta = 50.0
    adjusted = cv2.convertScaleAbs(gray, alpha=alpha, beta=beta)
    blur = cv2.bilateralFilter(adjusted, 11, 17, 17)
    sharpen_kernel = np.array([[-1, -1, -1], [-1, 9, -1], [-1, -1, -1]])
    sharpened = cv2.filter2D(blur, -1, sharpen_kernel)
    return sharpened

# Nhận diện và xử lý ảnh
def detect_all_text(image_path):
    print(f"📥 Đang xử lý ảnh: {image_path}")
    if not os.path.exists(image_path):
        print(f"❌ Không tìm thấy ảnh: {image_path}")
        return

    image = cv2.imread(image_path)
    if image is None:
        print("❌ Không đọc được ảnh.")
        return

    preprocessed = preprocess_image(image)
    try:
        results = reader.readtext(preprocessed, detail=1)
    except Exception as e:
        print(f"❌ Lỗi OCR: {e}")
        return

    if not results:
        print("❌ Không tìm thấy văn bản.")
        os.remove(image_path)  # Xóa ảnh không chứa biển số
        return

    results.sort(key=lambda r: sum([pt[1] for pt in r[0]]) / 4)
    all_texts = []
    all_points = []

    print("\n📋 Văn bản nhận diện:")
    for (bbox, text, confidence) in results:
        cleaned_text = re.sub(r'[^A-Za-z0-9]', '', text.strip())
        if len(cleaned_text) >= 3 and confidence > 0.5:
            print(f"🔹 '{cleaned_text}' | Độ chính xác: {confidence:.2f}")
            all_texts.append(cleaned_text)
            all_points.extend(bbox)

    if all_texts:
        plate_text = ' '.join(all_texts)
        
        # Lưu biển số vào file txt
        last_plate_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "last_plate.txt")
        with open(last_plate_path, "w", encoding="utf-8") as f:
            f.write(plate_text)
        print(f"✅ Đã lưu biển số: {plate_text}")

        # Cập nhật vào cơ sở dữ liệu
        db = connect_to_db()
        if db:
            cursor = db.cursor()

            # Kiểm tra xem biển số đã tồn tại chưa
            cursor.execute("SELECT * FROM vehicles WHERE bien_so = %s", (plate_text,))
            existing_vehicle = cursor.fetchone()

            if existing_vehicle:
                # Cập nhật cột ra = NOW()
                update_query = "UPDATE vehicles SET ra = NOW() WHERE bien_so = %s"
                cursor.execute(update_query, (plate_text,))
                print(f"✅ Đã cập nhật thời gian ra cho biển số: {plate_text}")
            else:
                # Thêm mới với ra = NOW()
                insert_query = "INSERT INTO vehicles (bien_so, ra) VALUES (%s, NOW())"
                cursor.execute(insert_query, (plate_text,))
                print(f"✅ Đã thêm biển số mới vào cơ sở dữ liệu với thời gian ra: {plate_text}")

            db.commit()
            cursor.close()
            db.close()
    else:
        print("❌ Không có văn bản hợp lệ.")
        os.remove(image_path)  # Xóa ảnh không hợp lệ

# Theo dõi thư mục tự động
class ImageHandler(FileSystemEventHandler):
    def on_created(self, event):
        if event.src_path.lower().endswith(('.jpg', '.jpeg', '.png')):
            time.sleep(1)  # Đợi file ghi xong
            detect_all_text(event.src_path)

def monitor_new_images():
    folder_path = r"C:\xampp\htdocs\doan1\anh"
    if not os.path.exists(folder_path):
        print(f"❌ Thư mục không tồn tại: {folder_path}")
        try:
            os.makedirs(folder_path, exist_ok=True)
            print(f"✅ Đã tạo thư mục: {folder_path}")
        except Exception as e:
            print(f"❌ Không thể tạo thư mục: {e}")
            return

    event_handler = ImageHandler()
    observer = Observer()
    observer.schedule(event_handler, folder_path, recursive=False)
    observer.start()
    print(f"🔄 Đang theo dõi thư mục: {folder_path}")

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
    observer.join()

if __name__ == "__main__":
    monitor_new_images()

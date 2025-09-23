from PIL import Image, ImageDraw, ImageFont

# 캔버스 생성
img = Image.new('RGB', (220, 48), color='#222')
draw = ImageDraw.Draw(img)

# 폰트 경로 (윈도우: 'arialbd.ttf', 맥: 'Arial Bold', 또는 NotoSansKR-Bold)
font_path = 'arialbd.ttf'  # 시스템에 따라 변경
font = ImageFont.truetype(font_path, 32)

# "Dance" 그린
draw.text((18, 6), "Dance", font=font, fill='#03C75A')

# "office" 흰색
draw.text((120, 6), "office", font=font, fill='#FFFFFF')

# 저장
img.save('danceoffice-logo.png')
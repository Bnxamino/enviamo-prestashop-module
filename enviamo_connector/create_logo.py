#!/usr/bin/env python3
"""
Crea un logo.png simple para el módulo PrestaShop
"""
from PIL import Image, ImageDraw, ImageFont

# Crear imagen 32x32
img = Image.new('RGB', (32, 32), color='#463cd4')

# Crear contexto de dibujo
draw = ImageDraw.Draw(img)

# Dibujar letra 'E' en el centro
try:
    # Intentar usar fuente Arial
    font = ImageFont.truetype("arial.ttf", 20)
except:
    # Si no está disponible, usar fuente por defecto
    font = ImageFont.load_default()

# Dibujar 'E' centrada
draw.text((8, 4), 'E', fill='white', font=font)

# Guardar en la ubicación correcta según dónde se ejecute
import os
if os.path.exists('enviamo_connector'):
    # Ejecutado desde la raíz del repo
    output_path = 'enviamo_connector/logo.png'
else:
    # Ejecutado desde dentro de enviamo_connector
    output_path = 'logo.png'

img.save(output_path)
print(f"Logo creado: {output_path}")
print(f"Tamanio: {img.size}")

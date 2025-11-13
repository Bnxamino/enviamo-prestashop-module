#!/usr/bin/env python3
"""
Script para crear ZIP del módulo PrestaShop con rutas Unix (/)
"""
import os
import zipfile
from pathlib import Path

def create_module_zip():
    # Directorios a incluir
    source_dir = Path('enviamo_connector')
    output_zip = Path('enviamo-connector-prestashop.zip')

    # Eliminar ZIP anterior si existe
    if output_zip.exists():
        output_zip.unlink()

    print(f"Creando ZIP: {output_zip}")

    # Crear ZIP con compresión
    with zipfile.ZipFile(output_zip, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # Recorrer todos los archivos en enviamo_connector
        for root, dirs, files in os.walk(source_dir):
            # Filtrar directorios a excluir
            dirs[:] = [d for d in dirs if not d.startswith('.') and d not in ['tests', 'node_modules', '__pycache__']]

            for file in files:
                # Filtrar archivos a excluir
                if file.startswith('.') or file.endswith('.pyc') or file.endswith('.py'):
                    continue

                file_path = Path(root) / file

                # Calcular ruta relativa desde enviamo_connector
                arcname = file_path.relative_to(source_dir)

                # Convertir a formato Unix (con /)
                arcname_unix = str(arcname).replace('\\', '/')

                # Añadir al ZIP
                zipf.write(file_path, arcname_unix)
                print(f"  [OK] {arcname_unix}")

    # Verificar contenido
    print(f"\nVerificando contenido del ZIP:")
    with zipfile.ZipFile(output_zip, 'r') as zipf:
        file_list = zipf.namelist()
        print(f"  Total archivos: {len(file_list)}")
        print(f"  Primeros 10:")
        for name in file_list[:10]:
            print(f"    - {name}")

    # Verificar que enviamo_connector.php está en raíz
    with zipfile.ZipFile(output_zip, 'r') as zipf:
        if 'enviamo_connector.php' in zipf.namelist():
            print(f"\n[SUCCESS] ZIP creado correctamente: {output_zip}")
            print(f"Tamanio: {output_zip.stat().st_size / 1024:.2f} KB")
        else:
            print(f"\n[ERROR] enviamo_connector.php no esta en la raiz del ZIP")
            return False

    return True

if __name__ == '__main__':
    success = create_module_zip()
    exit(0 if success else 1)

"""Exporta dos diagramas documentales sin acceder ni modificar la base de datos."""
from pathlib import Path
import re
import heapq
import html
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
SOURCE = (ROOT / 'esquema_relacional.mmd').read_text(encoding='utf-8')
tables = {}
for name, body in re.findall(r'^    (\w+) \{\n(.*?)^    \}', SOURCE, re.M | re.S):
    fields = []
    for line in body.splitlines():
        match = re.match(r'\s+(\w+) (\w+)\s*(.*?) "(.*?)"', line)
        if match:
            kind, field, keys, detail = match.groups()
            sql_type = detail.split(';')[0].replace(' unsigned', ' U')
            nullable = 'NOT NULL' not in detail
            fields.append((field, sql_type, keys.replace(', ', '/'), nullable))
    tables[name] = fields
relations = []
for parent, card, child, label in re.findall(r'^    (\w+) ([|o.{}\-]+) (\w+) : "([^"]+)"', SOURCE, re.M):
    child_key, parent_key = label.split(' a ')
    relations.append((child, child_key, parent, parent_key))

# Los dos grupos contienen todas las tablas del dominio, no las tablas internas de Laravel.
groups = [
    [
        ['municipios', 'localidades', 'jurisdicciones', 'tipo_unidades'],
        ['generos', 'nacionalidades', 'unidades_medicas', 'tipologias_unidades'],
        ['personas', 'users', 'roles', 'permisos'],
        ['usuario_rol', 'rol_permiso'],
    ],
    [
        ['personas', 'tipos_personal', 'personal_formacion', 'asesores', 'unidades_medicas'],
        ['instituciones', 'carreras', 'nivel_academico', 'datos_academicos', 'users'],
        ['adscripciones_personal', 'adscripciones_asesores', 'asigna_asesores', 'categorias', 'tipos_incidencias'],
        ['incidencias', 'tipos_consultas', 'productividad', 'tipos_documentos', 'materiales_consultas'],
        ['documentos_investigacion', 'observaciones_documentos'],
    ],
]
TITLES = ['Identidad, acceso y ubicación', 'Formación, asignaciones y seguimiento']
W, HEADER, ROW = 570, 66, 29
GAP, MARGIN = 115, 75
FONT_PATH = Path('C:/Windows/Fonts')


class Canvas:
    def __init__(self, width, height):
        self.width, self.height = width, height
        self.image = Image.new('RGB', (width, height), 'white')
        self.draw = ImageDraw.Draw(self.image)
        self.svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">', '<rect width="100%" height="100%" fill="white"/>']

    def rect(self, box, fill, stroke=None, radius=0):
        self.draw.rounded_rectangle(box, radius, fill, stroke, 2)
        x, y, right, bottom = box
        self.svg.append(f'<rect x="{x}" y="{y}" width="{right-x}" height="{bottom-y}" rx="{radius}" fill="{fill}" stroke="{stroke or "none"}" stroke-width="2"/>')

    def text(self, x, y, value, size=20, fill='#24343d', bold=False):
        font = ImageFont.truetype(str(FONT_PATH / ('arialbd.ttf' if bold else 'arial.ttf')), size)
        self.draw.text((x, y), value, font=font, fill=fill, anchor='lt')
        self.svg.append(f'<text x="{x}" y="{y}" dominant-baseline="text-before-edge" font-family="Arial, sans-serif" font-size="{size}" font-weight="{"bold" if bold else "normal"}" fill="{fill}">{html.escape(value)}</text>')

    def line(self, points, color, width=2):
        self.draw.line(points, fill=color, width=width, joint='curve')
        self.svg.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in points)}" fill="none" stroke="{color}" stroke-width="{width}" stroke-linejoin="round"/>')


def render(number, rows):
    refs = {'personas', 'users', 'unidades_medicas'} if number == 2 else set()
    shown, boxes = {}, {}
    y = 185
    for row in rows:
        for col, name in enumerate(row):
            fields = tables[name]
            if name in refs:
                fields = [field for field in fields if 'PK' in field[2]]
            shown[name] = fields
            x = MARGIN + col * (W + GAP)
            boxes[name] = (x, y, x + W, y + HEADER + ROW * len(fields) + 12)
        y = max(boxes[name][3] for name in row) + 100
    width = MARGIN * 2 + max(map(len, rows)) * (W + GAP) - GAP
    height = y + 160
    canvas = Canvas(width, height)
    canvas.text(MARGIN, 28, 'SIGEPF-JSVB | Modelo lógico y relacional', 36, '#263e3b', True)
    canvas.text(MARGIN, 79, f'Figura {number}. {TITLES[number-1]}', 30, '#722f42', True)
    canvas.text(MARGIN, 124, 'Atributos y claves del esquema existente · MySQL · Sin datos personales', 21, '#586772')
    active_relations = [r for r in relations if r[0] in shown and r[2] in shown and r[0] not in refs]
    ports = {}
    for name, fields in shown.items():
        left, top, right, bottom = boxes[name]
        for index, field in enumerate(fields):
            ports[(name, field[0])] = (left, right, top + HEADER + index * ROW + ROW // 2)

    # Trazado ortogonal: los conectores evitan el interior de todas las tablas.
    xs = sorted({20, width-20} | {v for box in boxes.values() for v in (box[0]-28, box[2]+28)})
    ys = sorted({160, y-35} | {v for box in boxes.values() for v in (box[1]-32, box[3]+32)} | {p[2] for p in ports.values()})
    obstacles = list(boxes.values())
    def clear(a, b):
        x1,y1 = a; x2,y2 = b
        for left,top,right,bottom in obstacles:
            if x1 == x2 and left-7 < x1 < right+7 and max(min(y1,y2),top-7) < min(max(y1,y2),bottom+7):
                return False
            if y1 == y2 and top-7 < y1 < bottom+7 and max(min(x1,x2),left-7) < min(max(x1,x2),right+7):
                return False
        return True
    graph = {}
    for i,x in enumerate(xs):
        for j,yy in enumerate(ys):
            point = (x,yy)
            graph[point] = []
            for ni,nj in [(i-1,j),(i+1,j),(i,j-1),(i,j+1)]:
                if 0 <= ni < len(xs) and 0 <= nj < len(ys):
                    other = (xs[ni],ys[nj])
                    if clear(point,other): graph[point].append(other)
    used = {}
    def route(start, end):
        todo = [(0,0,start,None)]
        dist = {(start,None):0}; previous = {}
        counter = 0
        while todo:
            _,cost,point,direction = heapq.heappop(todo)
            if cost > dist.get((point,direction),float('inf')): continue
            if point == end:
                path = [point]; state = (point,direction)
                while state in previous:
                    state = previous[state]; path.append(state[0])
                path.reverse()
                for a,b in zip(path,path[1:]): used[tuple(sorted((a,b)))] = used.get(tuple(sorted((a,b))),0)+1
                return path
            for other in graph[point]:
                nd = 0 if other[0] == point[0] else 1
                edge = tuple(sorted((point,other)))
                nc = cost + abs(other[0]-point[0])+abs(other[1]-point[1]) + (20 if direction is not None and direction != nd else 0) + used.get(edge,0)*45
                state = (other,nd)
                if nc < dist.get(state,float('inf')):
                    dist[state] = nc; previous[state] = (point,direction)
                    heapq.heappush(todo,(nc+abs(end[0]-other[0])+abs(end[1]-other[1]),nc,other,nd))
        raise RuntimeError(f'No connector path {start} -> {end}')
    colors = ['#536f8a','#8e5470','#45847a','#9b7438']
    for index,(child,cf,parent,pf) in enumerate(active_relations):
        left,_,cy = ports[(child,cf)]; _,right,py = ports[(parent,pf)]
        path = [(left,cy)] + route((left-28,cy),(right+28,py)) + [(right,py)]
        color = colors[index % len(colors)]
        canvas.line(path,'white',7); canvas.line(path,color,2)
        canvas.line([(right+10,py-5),(right,py),(right+10,py+5)],color,2)
        canvas.rect((left-4,cy-4,left+4,cy+4),color,radius=4)
    for name, fields in shown.items():
        left,top,right,bottom = boxes[name]
        is_ref = name in refs
        color = '#657481' if is_ref else ('#722f42' if name in ['personas','users','personal_formacion','documentos_investigacion'] else '#263e3b')
        canvas.rect((left,top,right,bottom),'#ffffff','#c5cfd4',8)
        canvas.rect((left,top,right,top+HEADER),color,radius=7)
        canvas.text(left+15,top+10,name,25,'#ffffff',True)
        canvas.text(left+15,top+40,'REFERENCIA · definición completa en figura 1' if is_ref else 'CLAVE     ATRIBUTO                                      TIPO SQL',15,'#e4eceb')
        for index,(field,sql_type,keys,nullable) in enumerate(fields):
            yy = top+HEADER+index*ROW
            if index%2 == 0: canvas.rect((left+2,yy,right-2,yy+ROW),'#f3f6f8')
            canvas.text(left+10,yy+5,keys,14,'#722f42',True)
            canvas.text(left+80,yy+4,field,19,'#24343d','PK' in keys)
            canvas.text(left+363,yy+5,sql_type+(' ?' if nullable else ''),16,'#52636d')
    canvas.text(MARGIN,y+5,'Lectura de las relaciones',23,'#263e3b',True)
    canvas.text(MARGIN,y+43,'Cada flecha parte de la FK y apunta a la clave referenciada. PK: primaria · FK: foránea · UK: única · ?: permite NULL · U: unsigned.',19)
    canvas.text(MARGIN,y+74,'FK sin UK: varios registros pueden referenciar el mismo padre. FK con UK o PK individual: como máximo uno. Varias PK: clave compuesta.',18)
    canvas.text(MARGIN,y+105,'Las tablas grises son referencias, no tablas duplicadas. Se omiten únicamente tablas internas de sesiones, caché y colas de Laravel.' if refs else 'La figura 2 continúa las relaciones de personas, users y unidades_medicas con formación y seguimiento.',18,'#586772')
    base = ROOT / f'modelo_relacional_parte_{number}'
    canvas.svg.append('</svg>')
    base.with_suffix('.svg').write_text('\n'.join(canvas.svg),encoding='utf-8')
    canvas.image.save(base.with_suffix('.png'),dpi=(300,300))
    canvas.image.save(base.with_suffix('.pdf'),'PDF',resolution=180)
    nodes = set(shown)
    out = ['erDiagram', '    direction LR']
    for name in shown:
        out += [f'    {name} {{']
        for field,sql_type,keys,nullable in shown[name]:
            out += [f'        {sql_type.split("(")[0].split()[0]} {field} {keys.replace("/", ", ")} "{sql_type}{"; NULL" if nullable else "; NOT NULL"}"']
        out += ['    }']
    out += [line for line in SOURCE.splitlines() if re.match(r'^    \w+ [|o.{}\-]+ \w+ :',line) and line.split()[0] in nodes and line.split()[2] in nodes and line.split()[2] not in refs]
    base.with_suffix('.mmd').write_text('\n'.join(out),encoding='utf-8')
    return {'figure':number,'tables':len(shown),'references':len(refs),'relationships':len(active_relations),'pixels':[width,height]}


if __name__ == '__main__':
    domain = set(tables)-{'migrations','cache','cache_locks','jobs','job_batches','failed_jobs','sessions','password_reset_tokens'}
    included = {name for group in groups for row in group for name in row}
    assert domain == included, f'Tablas sin representar: {domain-included}; desconocidas: {included-domain}'
    for number,group in enumerate(groups,1): print(render(number,group))

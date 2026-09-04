<?php

namespace App\Services;

use Illuminate\Support\Collection;
use ZipArchive;

class RegistrationExcelExporter
{
    public function create(Collection $registrations): string
    {
        $headers=['Kode Tiket','Status','Nama Perwakilan','Email','WhatsApp','NISN','Sekolah','Kota/Kabupaten Sekolah','Alamat Sekolah','Kelas','Jenis Lomba','Nama Lomba','Kota Pelaksanaan','Venue','Jadwal Kegiatan','Jadwal Lomba','Agenda Tambahan','Nama Tim','Ukuran Tim','Jumlah Official','Tanggal Daftar'];
        $rows=$registrations->map(fn($row)=>[
            $row->ticket_code, ucfirst($row->status), $row->full_name, $row->email, $row->whatsapp,
            $row->nisn, $row->school_name, $row->school_city, $row->school_address, $row->grade, $row->competition->category,
            $row->competition->title, $row->competitionSession?->city ?: $row->competition->location,
            $row->competitionSession?->venue ?: $row->competition->location,
            $this->dateRange($row->competitionSession?->activity_start_date, $row->competitionSession?->activity_end_date),
            $this->dateRange($row->competitionSession?->competition_start_date, $row->competitionSession?->competition_end_date),
            $row->competitionSession?->information_at
                ? trim(($row->competitionSession->information_label ?: 'Agenda').': '.$row->competitionSession->information_at->format('Y-m-d H:i'))
                : '-',
            $row->team_name ?: '-',
            $row->competition->participation_type==='team' ? $row->competition->team_size : 1,
            $row->officials->count(), $row->created_at?->format('Y-m-d H:i'),
        ])->prepend($headers)->values();

        return $this->createWorkbook(
            $rows,
            'Pendaftar Tervalidasi',
            '<col min="1" max="2" width="16" customWidth="1"/><col min="3" max="4" width="24" customWidth="1"/><col min="5" max="8" width="18" customWidth="1"/><col min="9" max="18" width="24" customWidth="1"/><col min="19" max="21" width="16" customWidth="1"/>'
        );
    }

    public function createSupporterTickets(Collection $tickets): string
    {
        $headers=['Kode Tiket','Status','Nama Supporter','Kelas','Asal Sekolah','Tempat BSI Flash','Kota Pelaksanaan','Alamat Tempat','Jenis Kelamin','Email','No. Telepon / WhatsApp','Berminat Kuliah Tahun Ini','Metode Pembayaran','Harga Tiket','Bukti Transfer','Catatan Verifikasi','Diverifikasi Oleh','Waktu Verifikasi','Tanggal Daftar'];
        $rows=$tickets->map(fn($ticket)=>[
            $ticket->ticket_code,
            match($ticket->status){'verified'=>'Terverifikasi','rejected'=>'Ditolak',default=>'Menunggu'},
            $ticket->full_name,
            $ticket->grade === 'other'
                ? 'Lainnya - '.($ticket->supporter_category === 'parent' ? 'Orang Tua' : 'Umum')
                : $ticket->grade,
            $ticket->school_name,
            $ticket->venue?->name ?: 'Belum ditentukan',
            $ticket->venue?->city ?: '-',
            $ticket->venue?->address ?: '-',
            $ticket->gender==='male' ? 'Laki-laki' : 'Perempuan',
            $ticket->email,
            $ticket->whatsapp,
            $ticket->interested_in_college ? 'Iya' : 'Tidak',
            $ticket->payment_method==='cash' ? 'Cash' : 'Transfer',
            (float) $ticket->ticket_price,
            $ticket->payment_proof_path ? 'Tersedia' : '-',
            $ticket->verification_note ?: '-',
            $ticket->verifier?->name ?: '-',
            $ticket->verified_at?->format('Y-m-d H:i') ?: '-',
            $ticket->created_at?->format('Y-m-d H:i'),
        ])->prepend($headers)->values();

        return $this->createWorkbook(
            $rows,
            'Tiket Supporter',
            '<col min="1" max="2" width="18" customWidth="1"/><col min="3" max="3" width="24" customWidth="1"/><col min="4" max="4" width="10" customWidth="1"/><col min="5" max="6" width="28" customWidth="1"/><col min="7" max="7" width="18" customWidth="1"/><col min="8" max="8" width="34" customWidth="1"/><col min="9" max="9" width="16" customWidth="1"/><col min="10" max="11" width="24" customWidth="1"/><col min="12" max="15" width="20" customWidth="1"/><col min="16" max="16" width="32" customWidth="1"/><col min="17" max="19" width="20" customWidth="1"/>'
        );
    }

    private function createWorkbook(Collection $rows, string $sheetName, string $columns): string
    {
        $path=tempnam(sys_get_temp_dir(),'nova-xlsx-');
        $zip=new ZipArchive();
        $zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',$this->contentTypes());
        $zip->addFromString('_rels/.rels',$this->rootRelationships());
        $zip->addFromString('xl/workbook.xml',$this->workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels',$this->workbookRelationships());
        $zip->addFromString('xl/styles.xml',$this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml',$this->worksheet($rows,$columns));
        $zip->close();
        return $path;
    }

    private function worksheet(Collection $rows, string $columns): string
    {
        $xmlRows='';
        foreach($rows as $rowIndex=>$row){
            $number=$rowIndex+1; $cells='';
            foreach($row as $columnIndex=>$value){
                $reference=$this->columnName($columnIndex+1).$number;
                if(is_int($value)||is_float($value))$cells.='<c r="'.$reference.'" s="'.($number===1?1:0).'"><v>'.$value.'</v></c>';
                else $cells.='<c r="'.$reference.'" t="inlineStr" s="'.($number===1?1:0).'"><is><t xml:space="preserve">'.$this->escape((string)$value).'</t></is></c>';
            }
            $xmlRows.='<row r="'.$number.'"'.($number===1?' ht="28" customHeight="1"':'').'>'.$cells.'</row>';
        }
        $last=max($rows->count(),1);
        $lastColumn=$this->columnName(count($rows->first() ?? [1]));
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$columns.'</cols><sheetData>'.$xmlRows.'</sheetData><autoFilter ref="A1:'.$lastColumn.$last.'"/></worksheet>';
    }

    private function dateRange($start, $end): string
    {
        if (! $start) return '-';
        $first=$start->format('Y-m-d');
        $last=$end?->format('Y-m-d');
        return ! $last || $last===$first ? $first : $first.' - '.$last;
    }

    private function columnName(int $number): string { $name=''; while($number>0){$number--; $name=chr(65+($number%26)).$name; $number=intdiv($number,26);} return $name; }
    private function escape(string $value): string { return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
    private function contentTypes(): string { return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>'; }
    private function rootRelationships(): string { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'; }
    private function workbook(string $sheetName): string { return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>'; }
    private function workbookRelationships(): string { return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'; }
    private function styles(): string { return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf></cellXfs></styleSheet>'; }
}

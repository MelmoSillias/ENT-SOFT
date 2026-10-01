<?php

namespace App\Impression\Application\Service;

use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

final class ImpressionDocumentSupport
{
    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly AgenceLogoUploadService $logoUploadService,
    ) {
    }

    public function settings(): array
    {
        return [
            'default_page_table' => $this->setting('IMPRESSION_PAGE_TABLE', 'a4'),
            'default_orientation_table' => $this->setting('IMPRESSION_ORIENTATION_TABLE', 'portrait'),
            'default_page_invoice' => $this->setting('IMPRESSION_PAGE_INVOICE', 'a4'),
            'default_orientation_invoice' => $this->setting('IMPRESSION_ORIENTATION_INVOICE', 'portrait'),
            'default_export_format' => $this->setting('IMPRESSION_DEFAULT_EXPORT_FORMAT', 'pdf'),
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '18'),
            'footer_text' => $this->setting('IMPRESSION_FOOTER_TEXT', ''),
        ];
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        $logo = $this->setting('AGENCE_LOGO_URL', '');
        $phone = $this->setting('AGENCE_TELEPHONE', '');
        $ville = $this->setting('AGENCE_VILLE', '');

        return [
            'shop_name' => $this->setting('AGENCE_NOM', 'ENT TECHNOLOGY'),
            'show_logo' => in_array(strtolower($this->setting('IMPRESSION_SHOW_LOGO', 'false')), ['1', 'true', 'yes', 'on'], true),
            'logo_url' => $this->logoUploadService->resolveForDocuments($logo ?: null),
            'address' => $this->setting('AGENCE_ADRESSE', ''),
            'ville' => $ville,
            'ville_bracket' => $ville !== '' ? '['.$ville.']' : '',
            'nina' => $this->setting('AGENCE_NINA', ''),
            'nif_fiscal' => $this->setting('AGENCE_NIF_FISCAL', ''),
            'phones' => $phone !== '' ? [$phone] : [],
            'phone' => $phone,
            'email' => $this->setting('AGENCE_EMAIL', ''),
            'website' => $this->setting('AGENCE_SITE_WEB', ''),
            'payee' => $this->setting('AGENCE_PAYEE', ''),
            'footer_text' => $this->setting('IMPRESSION_FOOTER_TEXT', ''),
            'address_lines' => array_values(array_filter([
                $this->setting('AGENCE_ADRESSE', ''),
                $ville,
            ])),
        ];
    }

    /** @return array<string, mixed> */
    public function pageContext(string $page, string $orientation): array
    {
        $sizes = [
            'a4' => ['portrait' => '210mm 297mm', 'landscape' => '297mm 210mm'],
            'a5' => ['portrait' => '148mm 210mm', 'landscape' => '210mm 148mm'],
        ];
        $format = $page === 'a5' ? 'a5' : 'a4';
        $orient = $orientation === 'landscape' ? 'landscape' : 'portrait';

        return [
            'format' => $format,
            'orientation' => $orient,
            'margin_mm' => (int) $this->setting('IMPRESSION_MARGIN_MM', '18'),
            'css_page_size' => $sizes[$format][$orient],
        ];
    }

    public function pdfResponse(string $html, string $filename, string $page, string $orientation, string $disposition): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Serif');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($page === 'a5' ? 'A5' : 'A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        $contentDisposition = ($disposition === 'attachment' ? 'attachment' : 'inline').'; filename="'.$filename.'.pdf"';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $contentDisposition,
        ]);
    }

    public function htmlResponse(string $html, string $filename, string $disposition = 'inline'): Response
    {
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => ($disposition === 'attachment' ? 'attachment' : 'inline').'; filename="'.$filename.'.html"',
        ]);
    }

    public function setting(string $cle, string $default): string
    {
        return $this->settingRepository->findByCle($cle)?->getValeur() ?? $default;
    }
}

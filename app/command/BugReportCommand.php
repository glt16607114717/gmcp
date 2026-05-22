<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\service\TapdService;

class BugReportCommand extends Command
{
    private TapdService $tapd;

    private const FONT_FILE = 'msyh.ttc';
    private const FONT_SIZE = 14;
    private const HEADER_FONT_SIZE = 15;
    private const TITLE_FONT_SIZE = 20;
    private const PADDING = 16;
    private const ROW_HEIGHT = 36;
    private const HEADER_HEIGHT = 40;
    private const TITLE_HEIGHT = 56;

    private const COLOR_BG = [245, 247, 250];
    private const COLOR_TITLE_BG = [46, 82, 154];
    private const COLOR_HEADER_BG = [230, 236, 247];
    private const COLOR_ROW_EVEN = [255, 255, 255];
    private const COLOR_ROW_ODD = [245, 247, 250];
    private const COLOR_TOTAL_BG = [230, 236, 247];
    private const COLOR_TEXT = [51, 51, 51];
    private const COLOR_WHITE = [255, 255, 255];
    private const COLOR_PENDING = [204, 68, 0];
    private const COLOR_BORDER = [200, 210, 225];
    private const COLOR_ZERO = [180, 180, 180];

    protected function configure()
    {
        $this->setName('bug:report')
            ->addArgument('action', Argument::OPTIONAL, 'show|send', 'show')
            ->setDescription('TAPD团队Bug与任务统计报告');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action') ?: 'show';
        $this->tapd = new TapdService();

        $output->writeln('<info>正在采集TAPD数据...</info>');

        $developers = $this->getDevelopers();
        $iterations = $this->getRecentIterations(2);
        $months = $this->getRecentMonths(3);

        $output->writeln("<info>开发人员: " . count($developers) . "人</info>");
        $output->writeln("<info>统计月份: " . implode(', ', array_column($months, 'label')) . "</info>");

        $results = $this->collectData($developers, $months, $iterations, $output);

        if ($action === 'show') {
            $text = $this->buildText($results, $months, $iterations);
            $output->writeln('');
            $output->writeln($text);
            $output->writeln('');
            $imagePath = $this->buildTableImage($results, $months, $iterations);
            $output->writeln("<info>图片已生成: {$imagePath}</info>");
            $output->writeln('<info>使用 bug:report send 发送到企业微信</info>');
        } elseif ($action === 'send') {
            $imagePath = $this->buildTableImage($results, $months, $iterations);
            $output->writeln("<info>图片已生成: {$imagePath}</info>");
            $this->sendImageToWecom($imagePath);
            $output->writeln('<info>已发送到企业微信群</info>');
        }
    }

    private function getDevelopers(): array
    {
        $now = new \DateTime();
        $start = (clone $now)->modify('first day of this month')->format('Y-m-d');
        $end = (clone $now)->modify('last day of this month')->format('Y-m-d');

        $bugs = $this->tapd->getBugs([
            'fields' => 'fixer',
            'limit' => 200,
            'created' => "{$start}~{$end}",
        ]);

        $lastMonth = (clone $now)->modify('-1 month');
        $lmStart = $lastMonth->format('Y-m-01');
        $lmEnd = $lastMonth->format('Y-m-t');
        $bugsLm = $this->tapd->getBugs([
            'fields' => 'fixer',
            'limit' => 200,
            'created' => "{$lmStart}~{$lmEnd}",
        ]);

        $fixers = [];
        foreach (array_merge($bugs, $bugsLm) as $item) {
            $fixer = trim($item['Bug']['fixer'] ?? '');
            if ($fixer !== '') {
                $fixers[$fixer] = true;
            }
        }

        return array_keys($fixers);
    }

    private function getRecentIterations(int $count): array
    {
        $allIters = $this->tapd->getIterations();
        $result = [];
        foreach ($allIters as $item) {
            $it = $item['Iteration'] ?? [];
            $result[] = [
                'id' => $it['id'],
                'name' => $it['name'],
                'startdate' => $it['startdate'] ?? '',
                'enddate' => $it['enddate'] ?? '',
            ];
            if (count($result) >= $count) {
                break;
            }
        }
        return $result;
    }

    private function getRecentMonths(int $count): array
    {
        $months = [];
        for ($i = 1; $i <= $count; $i++) {
            $dt = new \DateTime("first day of -{$i} month");
            $months[] = [
                'label' => $dt->format('n') . '月',
                'start' => $dt->format('Y-m-01'),
                'end' => $dt->format('Y-m-t'),
            ];
        }
        return array_reverse($months);
    }

    private function collectData(array $developers, array $months, array $iterations, Output $output): array
    {
        $results = [];
        $total = count($developers);
        $idx = 0;

        foreach ($developers as $dev) {
            $idx++;
            $output->writeln("  [{$idx}/{$total}] {$dev}...");

            $row = ['monthly' => [], 'pending' => 0, 'iter_bug' => [], 'iter_task' => []];

            foreach ($months as $m) {
                $row['monthly'][$m['label']] = $this->tapd->getBugCount([
                    'fixer' => $dev,
                    'created' => "{$m['start']}~{$m['end']}",
                ]);
            }

            $row['pending'] = $this->tapd->getBugCount([
                'current_owner' => $dev,
                'status' => 'new|in_progress|reopened',
            ]);

            foreach ($iterations as $it) {
                $row['iter_bug'][$it['name']] = $this->tapd->getBugCount([
                    'current_owner' => $dev,
                    'iteration_id' => $it['id'],
                ]);
                $totalTasks = $this->tapd->getTaskCount([
                    'owner' => $dev,
                    'iteration_id' => $it['id'],
                ]);
                $doneTasks = $this->tapd->getTaskCount([
                    'owner' => $dev,
                    'iteration_id' => $it['id'],
                    'status' => 'done',
                ]);
                $row['iter_task'][$it['name']] = ['total' => $totalTasks, 'done' => $doneTasks];
            }

            $results[$dev] = $row;
        }

        return $results;
    }

    private function buildTableData(array $results, array $months, array $iterations): array
    {
        $iterShortNames = [];
        foreach ($iterations as $it) {
            $iterShortNames[$it['name']] = str_replace(['迭代目标', '迭代规划'], '', $it['name']);
        }

        $headers = ['开发人员'];
        foreach ($months as $m) {
            $headers[] = $m['label'] . 'Bug';
        }
        $headers[] = '待处理';
        foreach ($iterations as $it) {
            $headers[] = $iterShortNames[$it['name']] . 'Bug';
        }
        foreach ($iterations as $it) {
            $headers[] = $iterShortNames[$it['name']] . '任务';
        }

        $totals = array_fill(0, count($headers), 0);
        $rows = [];
        $pendingColIdx = count($months) + 1;

        foreach ($results as $dev => $row) {
            $cols = [$dev];
            $colIdx = 0;
            foreach ($months as $m) {
                $v = $row['monthly'][$m['label']] ?? 0;
                $cols[] = (string)$v;
                $totals[++$colIdx] += $v;
            }
            $pending = $row['pending'];
            $cols[] = (string)$pending;
            $totals[++$colIdx] += $pending;
            foreach ($iterations as $it) {
                $v = $row['iter_bug'][$it['name']] ?? 0;
                $cols[] = (string)$v;
                $totals[++$colIdx] += $v;
            }
            foreach ($iterations as $it) {
                $t = $row['iter_task'][$it['name']]['total'] ?? 0;
                $d = $row['iter_task'][$it['name']]['done'] ?? 0;
                $cols[] = "{$d}/{$t}";
                $totals[++$colIdx] += $d;
            }
            $rows[] = $cols;
        }

        $totalRow = ['合计'];
        for ($i = 1; $i < count($headers); $i++) {
            if (isset($headers[$i]) && str_contains($headers[$i], '任务')) {
                $totalRow[] = $totals[$i] . '/' . $totals[$i];
            } else {
                $totalRow[] = (string)$totals[$i];
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'totalRow' => $totalRow,
            'pendingColIdx' => $pendingColIdx,
        ];
    }

    private function buildTableImage(array $results, array $months, array $iterations): string
    {
        $data = $this->buildTableData($results, $months, $iterations);
        $headers = $data['headers'];
        $rows = $data['rows'];
        $totalRow = $data['totalRow'];
        $pendingColIdx = $data['pendingColIdx'];
        $colCount = count($headers);
        $rowCount = count($rows);

        $fontPath = public_path() . self::FONT_FILE;
        $fontSize = self::FONT_SIZE;
        $padding = self::PADDING;

        $colWidths = [];
        for ($c = 0; $c < $colCount; $c++) {
            $maxW = $this->textWidth($headers[$c], $fontPath, $fontSize);
            foreach ($rows as $row) {
                $w = $this->textWidth($row[$c] ?? '', $fontPath, $fontSize);
                if ($w > $maxW) $maxW = $w;
            }
            $tw = $this->textWidth($totalRow[$c] ?? '', $fontPath, $fontSize);
            if ($tw > $maxW) $maxW = $tw;
            $colWidths[$c] = $maxW + $padding * 2;
        }

        $tableWidth = array_sum($colWidths);
        $imgWidth = $tableWidth + 2;
        $imgHeight = self::TITLE_HEIGHT + self::HEADER_HEIGHT + ($rowCount + 1) * self::ROW_HEIGHT + 2;

        $img = imagecreatetruecolor($imgWidth, $imgHeight);
        imagesavealpha($img, true);
        $bgColor = $this->color($img, self::COLOR_BG);
        imagefill($img, 0, 0, $bgColor);

        $titleColor = $this->color($img, self::COLOR_WHITE);
        $titleBg = $this->color($img, self::COLOR_TITLE_BG);
        imagefilledrectangle($img, 0, 0, $imgWidth, self::TITLE_HEIGHT, $titleBg);

        $now = new \DateTime();
        $titleText = '产研团队日报 - ' . $now->format('Y-m-d');
        $titleX = ($imgWidth - $this->textWidth($titleText, $fontPath, self::TITLE_FONT_SIZE)) / 2;
        imagettftext($img, self::TITLE_FONT_SIZE, 0, (int)$titleX, (int)(self::TITLE_HEIGHT * 0.65), $titleColor, $fontPath, $titleText);

        $y = self::TITLE_HEIGHT;

        $headerTextY = (int)(self::HEADER_HEIGHT * 0.63);
        $rowTextY = (int)(self::ROW_HEIGHT * 0.62);

        $headerBg = $this->color($img, self::COLOR_HEADER_BG);
        $headerColor = $this->color($img, self::COLOR_TEXT);
        imagefilledrectangle($img, 0, $y, $imgWidth, $y + self::HEADER_HEIGHT, $headerBg);
        $x = 1;
        for ($c = 0; $c < $colCount; $c++) {
            $textX = $x + ($colWidths[$c] - $this->textWidth($headers[$c], $fontPath, $fontSize)) / 2;
            imagettftext($img, $fontSize, 0, (int)$textX, $y + $headerTextY, $headerColor, $fontPath, $headers[$c]);
            $x += $colWidths[$c];
        }
        $y += self::HEADER_HEIGHT;

        $borderColor = $this->color($img, self::COLOR_BORDER);
        $textColor = $this->color($img, self::COLOR_TEXT);
        $pendingColor = $this->color($img, self::COLOR_PENDING);
        $zeroColor = $this->color($img, self::COLOR_ZERO);

        for ($r = 0; $r < $rowCount; $r++) {
            $rowBg = ($r % 2 === 0) ? $this->color($img, self::COLOR_ROW_EVEN) : $this->color($img, self::COLOR_ROW_ODD);
            imagefilledrectangle($img, 0, $y, $imgWidth, $y + self::ROW_HEIGHT, $rowBg);

            $x = 1;
            for ($c = 0; $c < $colCount; $c++) {
                $val = $rows[$r][$c] ?? '';

                if ($c === 0) {
                    imagettftext($img, $fontSize, 0, $x + $padding, $y + $rowTextY, $textColor, $fontPath, $val);
                } elseif ($c === $pendingColIdx) {
                    $drawColor = (int)$val > 0 ? $pendingColor : $zeroColor;
                    $textX = $x + ($colWidths[$c] - $this->textWidth($val, $fontPath, $fontSize)) / 2;
                    imagettftext($img, $fontSize, 0, (int)$textX, $y + $rowTextY, $drawColor, $fontPath, $val);
                } else {
                    $drawColor = ($val === '0') ? $zeroColor : $textColor;
                    $textX = $x + ($colWidths[$c] - $this->textWidth($val, $fontPath, $fontSize)) / 2;
                    imagettftext($img, $fontSize, 0, (int)$textX, $y + $rowTextY, $drawColor, $fontPath, $val);
                }
                $x += $colWidths[$c];
            }

            imageline($img, 0, $y + self::ROW_HEIGHT, $imgWidth, $y + self::ROW_HEIGHT, $borderColor);
            $y += self::ROW_HEIGHT;
        }

        $totalBg = $this->color($img, self::COLOR_TOTAL_BG);
        imagefilledrectangle($img, 0, $y, $imgWidth, $y + self::ROW_HEIGHT, $totalBg);
        $x = 1;
        for ($c = 0; $c < $colCount; $c++) {
            $val = $totalRow[$c] ?? '';
            if ($c === 0) {
                imagettftext($img, $fontSize, 0, $x + $padding, $y + $rowTextY, $textColor, $fontPath, $val);
            } else {
                $textX = $x + ($colWidths[$c] - $this->textWidth($val, $fontPath, $fontSize)) / 2;
                $drawColor = ($c === $pendingColIdx) ? $pendingColor : $textColor;
                imagettftext($img, $fontSize, 0, (int)$textX, $y + $rowTextY, $drawColor, $fontPath, $val);
            }
            $x += $colWidths[$c];
        }

        $savePath = runtime_path() . 'bug_report_' . date('Ymd_His') . '.png';
        imagepng($img, $savePath);
        imagedestroy($img);

        return $savePath;
    }

    private function buildText(array $results, array $months, array $iterations): string
    {
        $data = $this->buildTableData($results, $months, $iterations);
        $lines = [];
        $lines[] = '|' . implode('|', $data['headers']) . '|';
        $seps = array_map(fn($h) => str_repeat('-', max(mb_strlen($h, 'UTF-8') * 2, 6)), $data['headers']);
        $lines[] = '|' . implode('|', $seps) . '|';
        foreach ($data['rows'] as $row) {
            $lines[] = '|' . implode('|', $row) . '|';
        }
        $lines[] = '|' . implode('|', $data['totalRow']) . '|';
        return implode("\n", $lines);
    }

    private function textWidth(string $text, string $font, float $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);
        return (int)($box[2] - $box[0] + 2);
    }

    private function color($img, array $rgb)
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function sendImageToWecom(string $imagePath): void
    {
        $config = \think\facade\Config::get('mcp_environments');
        $webhookUrl = $config['wecom_bot']['webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            throw new \Exception('未配置企业微信机器人webhook地址');
        }

        if (!file_exists($imagePath)) {
            throw new \Exception("图片文件不存在: {$imagePath}");
        }

        $imageData = file_get_contents($imagePath);
        $base64 = base64_encode($imageData);
        $md5 = md5_file($imagePath);

        \app\service\HttpClientService::request('POST', $webhookUrl, [
            'msgtype' => 'image',
            'image' => [
                'base64' => $base64,
                'md5' => $md5,
            ],
        ], ['content_type' => 'json']);
    }
}

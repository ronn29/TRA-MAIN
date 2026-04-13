<?php
session_start();
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit();
}

$school_id = $_SESSION['school_id'];

$query = "SELECT * FROM student_tbl WHERE school_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    die("Student not found");
}

$hasImageSupport = extension_loaded('gd') || extension_loaded('imagick');
if (!$hasImageSupport && !empty($student['profile_picture'])) {
}

$tcpdf_loaded = false;
if (file_exists('../libs/tcpdf/tcpdf.php')) {
    require_once('../libs/tcpdf/tcpdf.php');
    $tcpdf_loaded = true;
} elseif (file_exists('../tcpdf/tcpdf.php')) {
    require_once('../tcpdf/tcpdf.php');
    $tcpdf_loaded = true;
} elseif (file_exists('../vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');
    $tcpdf_loaded = true;
}

if (!$tcpdf_loaded) {
    die("TCPDF library not found. Please install TCPDF. See INSTALL_TCPDF.md for instructions.");
}

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$pdf->SetCreator('TRAGABAY Resume Builder');
$pdf->SetAuthor($fullName);
$pdf->SetTitle('Resume - ' . $fullName);
$pdf->SetSubject('Resume');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(12, 10, 12);
$pdf->SetAutoPageBreak(true, 8);

$pdf->AddPage();

$pdf->SetFont('helvetica', '', 9);

$yPos = 12;

if (!empty($student['profile_picture'])) {
    $basePath = dirname(__DIR__); // Project root directory
    $profilePic = $student['profile_picture'];
    
    $profilePic = ltrim($profilePic, './\\');
    
    $possiblePaths = [
        $basePath . DIRECTORY_SEPARATOR . $profilePic,
        $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profilePic),
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $profilePic,
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profilePic),
    ];
    
    $imagePath = null;
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            $imagePath = $realPath;
            break;
        }
    }
    
    if (!$imagePath && file_exists($student['profile_picture'])) {
        $imagePath = realpath($student['profile_picture']);
    }
    
    if ($imagePath) {
        $imageInfo = @getimagesize($imagePath);
        
        if ($imageInfo !== false) {
            $imageType = $imageInfo[2];
            
            try {
                $format = '';
                $tempJpeg = null;
                $originalImagePath = $imagePath;
                
                if ($imageType == IMAGETYPE_JPEG) {
                    $format = 'JPG';
                } elseif ($imageType == IMAGETYPE_PNG) {
                    if (extension_loaded('gd')) {
                        $tempJpeg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'resume_pic_' . $school_id . '_' . time() . '.jpg';
                        $pngImage = @imagecreatefrompng($imagePath);
                        
                        if ($pngImage !== false) {
                            $width = imagesx($pngImage);
                            $height = imagesy($pngImage);
                            $jpegImage = imagecreatetruecolor($width, $height);
                            $white = imagecolorallocate($jpegImage, 255, 255, 255);
                            imagefill($jpegImage, 0, 0, $white);
                            imagecopy($jpegImage, $pngImage, 0, 0, 0, 0, $width, $height);
                            
                            if (imagejpeg($jpegImage, $tempJpeg, 95)) {
                                $imagePath = $tempJpeg;
                                $format = 'JPG';
                            } else {
                                $format = 'PNG';
                            }
                            
                            imagedestroy($pngImage);
                            imagedestroy($jpegImage);
                        } else {
                            $format = 'PNG';
                        }
                    } else {
                        if (extension_loaded('imagick')) {
                            $format = 'PNG';
                        } else {
                            $format = 'PNG';
                        }
                    }
                } elseif ($imageType == IMAGETYPE_GIF) {
                    $format = 'GIF';
                }
                
                if ($format && $imagePath) {
                    $xPos = 88;
                    $yPosImg = 8;
                    $imgWidth = 30;
                    $imgHeight = 30;
                    
                    try {
                        $pdf->Image($imagePath, $xPos, $yPosImg, $imgWidth, $imgHeight, $format);
                        $yPos = 42;
                    } catch (Exception $imgError) {
                        try {
                            $pdf->Image($imagePath, $xPos, $yPosImg, $imgWidth, $imgHeight, $format, '', '', false, 300, '', false, false, 0);
                            $yPos = 42;
                        } catch (Exception $imgError2) {
                            try {
                            $pdf->Image($imagePath, $xPos, $yPosImg, $imgWidth, $imgHeight);
                            $yPos = 42;
                            } catch (Exception $imgError3) {
                                $yPos = 12;
                            }
                        }
                    }
                }
                
                if ($tempJpeg && file_exists($tempJpeg)) {
                    @unlink($tempJpeg);
                }
            } catch (Exception $e) {
                $yPos = 12;
            }
        }
    }
}

if (!empty($fullName)) {
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 7, $fullName, 0, 1, 'C');
    $yPos += 6;
}

$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(102, 102, 102);
$contactInfo = [];
if (!empty($student['email'])) $contactInfo[] = $student['email'];
if (!empty($student['contact_number'])) $contactInfo[] = $student['contact_number'];
if (!empty($student['address'])) $contactInfo[] = $student['address'];
if (!empty($student['linkedin_profile'])) $contactInfo[] = 'LinkedIn: ' . $student['linkedin_profile'];

if (!empty($contactInfo)) {
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 5, implode(' | ', $contactInfo), 0, 1, 'C');
    $yPos += 8;
}

if (!empty($student['personal_statement'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Personal Statement / Objective', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $statement = $student['personal_statement'];
    $sentences = preg_split('/(?<=[.!?])\s+/', $statement);
    if (count($sentences) > 3) {
        $statement = implode(' ', array_slice($sentences, 0, 3));
    }
    $pdf->MultiCell(0, 4.5, $statement, 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['education'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Education', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $education = $student['education'];
    $lines = explode("\n", $education);
    $formatted = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $formatted .= '• ' . $line . "\n";
        }
    }
    $pdf->MultiCell(0, 4.5, trim($formatted), 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['work_experience'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Work Experience', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $workExp = $student['work_experience'];
    $lines = explode("\n", $workExp);
    $formatted = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            if (strpos($line, '•') !== 0 && strpos($line, '-') !== 0) {
                $formatted .= '• ' . $line . "\n";
            } else {
                $formatted .= $line . "\n";
            }
        }
    }
    $pdf->MultiCell(0, 4.5, trim($formatted), 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['skills'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Skills', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $skills = $student['skills'];
    $lines = explode("\n", $skills);
    $formatted = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            if (strpos($line, '•') !== 0 && strpos($line, '-') !== 0) {
                $formatted .= '• ' . $line . "\n";
            } else {
                $formatted .= $line . "\n";
            }
        }
    }
    $pdf->MultiCell(0, 4.5, trim($formatted), 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['extracurricular'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Extracurricular Activities & Leadership Roles', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $extracurricular = $student['extracurricular'];
    $lines = explode("\n", $extracurricular);
    $formatted = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            if (strpos($line, '•') !== 0 && strpos($line, '-') !== 0) {
                $formatted .= '• ' . $line . "\n";
            } else {
                $formatted .= $line . "\n";
            }
        }
    }
    $pdf->MultiCell(0, 4.5, trim($formatted), 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['awards'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'Awards & Achievements', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $awards = $student['awards'];
    $lines = explode("\n", $awards);
    $formatted = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            if (strpos($line, '•') !== 0 && strpos($line, '-') !== 0) {
                $formatted .= '• ' . $line . "\n";
            } else {
                $formatted .= $line . "\n";
            }
        }
    }
    $pdf->MultiCell(0, 4.5, trim($formatted), 0, 'L', false, 1);
    $yPos += $pdf->getLastH() + 4;
}

if (!empty($student['ref'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(50, 129, 219);
    $pdf->SetXY(12, $yPos);
    $pdf->Cell(0, 6, 'References', 0, 1, 'L');
    $yPos += 5;
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(12, $yPos);
    $pdf->MultiCell(0, 4.5, $student['ref'], 0, 'L', false, 1);
}

$filename = str_replace(' ', '_', $fullName) . '_Resume.pdf';
if (empty($filename) || $filename === '_Resume.pdf') {
    $filename = 'Resume_' . $school_id . '.pdf';
}

$pdf->Output($filename, 'D');
exit;
?>


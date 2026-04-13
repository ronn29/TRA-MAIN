<?php

function getAllStudentAnswers($conn, $student_id)
{
    $answers = [];

    $sql = "SELECT answer 
            FROM assessment_results_tbl
            WHERE student_id = ?
              AND assessment_status = 'Completed'
            ORDER BY assessment_id ASC, question_id ASC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $answers[] = intval($row['answer']) === 1 ? 1 : 0;
        }

        mysqli_stmt_close($stmt);
    }

    return $answers;
}

function getStudentAssessmentResults($conn, $assessment_id, $student_id)
{
    $answers = [];
    $sql = "SELECT question_id, answer 
            FROM assessment_results_tbl 
            WHERE assessment_id = ? AND student_id = ? AND assessment_status = 'Completed'";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $assessment_id, $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $answers[$row['question_id']] = intval($row['answer']) === 1 ? 1 : 0;
        }

        mysqli_stmt_close($stmt);
    }

    return $answers;
}

function getStudentProgramCode($conn, $student_id)
{
    $sql = "SELECT p.program_code, p.program_name, p.program_id, p.department_id
            FROM student_tbl s
            LEFT JOIN program_tbl p ON s.program_id = p.program_id
            WHERE s.school_id = ?
            LIMIT 1";

    $code = 'Unknown';
    $programId = null;
    $programName = null;
    $departmentId = null;

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $programId = $row['program_id'] ?? null;
            $programName = $row['program_name'] ?? null;
            $departmentId = $row['department_id'] ?? null;

            if (!empty($row['program_code'])) {
                $code = $row['program_code'];
            } elseif (!empty($programName)) {
                $code = $programName;
            }
        }

        mysqli_stmt_close($stmt);
    }

    return [$code, $programId, $programName, $departmentId];
}

function getCareersForProgram($conn, $program_id, $department_id = null)
{
    $careers = [];

    if ($program_id) {
        $sql = "SELECT career_name 
                FROM career_program_tbl 
                WHERE is_active = 1 AND program_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $program_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['career_name'])) {
                    $careers[] = $row['career_name'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (empty($careers) && $department_id) {
        $sql = "SELECT career_name 
                FROM career_program_tbl 
                WHERE is_active = 1 AND department_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $department_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['career_name'])) {
                    $careers[] = $row['career_name'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    return $careers;
}

function getCareerDescriptions($conn, array $careerNames, $program_id)
{
    if (empty($careerNames)) return [];

    $placeholders = implode(',', array_fill(0, count($careerNames), '?'));
    $types = str_repeat('s', count($careerNames));

    $sql = "SELECT career_name, career_description 
            FROM career_program_tbl 
            WHERE career_name IN ($placeholders)";

    $params = $careerNames;
    $bindTypes = $types;

    if (!empty($program_id)) {
        $sql .= " AND (program_id = ? OR program_id IS NULL)";
        $bindTypes .= 'i';
        $params[] = $program_id;
    }

    $descriptions = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $name = $row['career_name'];
            if (!isset($descriptions[$name])) {
                $descriptions[$name] = $row['career_description'] ?? '';
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $descriptions;
}

function getCareerResources($conn, array $careerNames)
{
    if (empty($careerNames)) return [];

    $placeholders = implode(',', array_fill(0, count($careerNames), '?'));
    $types = str_repeat('s', count($careerNames));

    $sql = "SELECT career_name, resource_name, resource_url, resource_type, resource_provider, is_free, display_order
            FROM career_resources_tbl 
            WHERE career_name IN ($placeholders) 
              AND is_active = 1
            ORDER BY career_name ASC, display_order ASC, resource_id ASC";

    $resources = [];
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $types, ...$careerNames);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $career = $row['career_name'];
            
            $resource = [
                'name' => $row['resource_name'],
                'url' => $row['resource_url'],
                'type' => $row['resource_type'],
                'provider' => $row['resource_provider'],
                'is_free' => (bool)$row['is_free'],
                'display_order' => (int)$row['display_order']
            ];
            
            if (!isset($resources[$career])) {
                $resources[$career] = [
                    'all' => [],
                    'certifications' => [],
                    'courses' => [],
                    'learning_paths' => [],
                    'free' => []
                ];
            }
            
            $resources[$career]['all'][] = $resource;
            
            switch ($row['resource_type']) {
                case 'certification':
                    $resources[$career]['certifications'][] = $resource;
                    break;
                case 'course':
                    $resources[$career]['courses'][] = $resource;
                    break;
                case 'learning_path':
                    $resources[$career]['learning_paths'][] = $resource;
                    break;
            }
            
            if ($row['is_free'] == 1) {
                $resources[$career]['free'][] = $resource;
            }
        }
        
        mysqli_stmt_close($stmt);
    }

    return $resources;
}

function calculateResourceRelevance($resource)
{
    $score = 0;
    
    $score += (100 - $resource['display_order'] * 5);
    
    if ($resource['is_free']) {
        $score += 30;
    }
    
    if ($resource['type'] === 'certification') {
        $score += 25;
    }
    
    $premiumProviders = ['Google', 'Microsoft', 'AWS', 'IBM', 'Oracle', 'Cisco', 'CompTIA', 'ISTQB', 'PMI'];
    if (in_array($resource['provider'], $premiumProviders)) {
        $score += 20;
    }
    
    $trustedPlatforms = ['Coursera', 'edX', 'MIT', 'Harvard', 'Stanford', 'freeCodeCamp', 'IEEE', 'ACM'];
    if (in_array($resource['provider'], $trustedPlatforms)) {
        $score += 15;
    }
    
    if ($resource['type'] === 'learning_path') {
        $score += 10;
    }
    
    if ($resource['type'] === 'course') {
        $score += 5;
    }
    
    return $score;
}

function getSimpleCareerResources($conn, array $careerNames)
{
    $detailedResources = getCareerResources($conn, $careerNames);
    $simpleResources = [];
    
    foreach ($detailedResources as $career => $groups) {
        $simpleResources[$career] = [];
        
        $scoredResources = [];
        foreach ($groups['all'] as $resource) {
            $resource['relevance_score'] = calculateResourceRelevance($resource);
            $scoredResources[] = $resource;
        }
        
        usort($scoredResources, function($a, $b) {
            if ($b['relevance_score'] != $a['relevance_score']) {
                return $b['relevance_score'] - $a['relevance_score'];
            }
            return $a['display_order'] - $b['display_order'];
        });
        
        foreach ($scoredResources as $resource) {
            $displayName = $resource['name'];
            
            $badges = [];
            if ($resource['is_free']) {
                $badges[] = 'Free';
            }
            if ($resource['type'] === 'certification') {
                $badges[] = 'Certification';
            }
            if (!empty($resource['provider'])) {
                $badges[] = $resource['provider'];
            }
            
            if (!empty($badges)) {
                $displayName .= ' (' . implode(' • ', $badges) . ')';
            }
            
            $simpleResources[$career][$displayName] = $resource['url'];
        }
    }
    
    return $simpleResources;
}

function buildRfFeatureVector(array $orderedAnswers)
{
    $featureNames = array_merge(
        ['Program_Encoded'],
        array_map(fn($i) => "Apt_Q{$i}", range(1, 10)),
        array_map(fn($i) => "Int_Q{$i}", range(1, 10)),
        array_map(fn($i) => "Pers_Q{$i}", range(1, 10)),
        array_map(fn($i) => "CS_Q{$i}", range(1, 10)),
        array_map(fn($i) => "IT_Q{$i}", range(1, 10)),
        array_map(fn($i) => "IS_Q{$i}", range(1, 10)),
        array_map(fn($i) => "ACT_Q{$i}", range(1, 10)),
    );

    $featureValues = [];
    $answerIndex = 0;

    foreach ($featureNames as $name) {
        if ($name === 'Program_Encoded') continue;

        $featureValues[$name] = $orderedAnswers[$answerIndex] ?? -1;
        $answerIndex++;
    }

    return $featureValues;
}

function loadCareerWeightsMap($useIed = false, $customSuffix = null)
{
    $suffix = $customSuffix ?? ($useIed ? '_ied' : '');
    $jsonFile = __DIR__ . "/../../ml/career_weights_map{$suffix}.json";
    
    if (file_exists($jsonFile)) {
        $content = @file_get_contents($jsonFile);
        if ($content) {
            $weightsMap = @json_decode($content, true);
            if (is_array($weightsMap) && !empty($weightsMap)) {
                return $weightsMap;
            }
        }
    }
    
    $pythonFile = __DIR__ . "/../../ml/career_weights_map{$suffix}.py";
    
    if (!file_exists($pythonFile)) {
        return [];
    }

    $content = file_get_contents($pythonFile);
    $weightsMap = [];
    
    if (preg_match_all('/"([^"]+)":\s*\{([^}]+)\}/', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $career = $match[1];
            $weightsStr = $match[2];
            $weights = [];
            
            if (preg_match_all('/"([^"]+)":\s*(\d+)/', $weightsStr, $weightMatches, PREG_SET_ORDER)) {
                foreach ($weightMatches as $wm) {
                    $weights[$wm[1]] = (int)$wm[2];
                }
            }
            
            if (!empty($weights)) {
                $weightsMap[$career] = $weights;
            }
        }
    }
    
    return $weightsMap;
}

function standardizeProgramCode($programCode, $programName)
{
    $programMapping = [
        'IED' => ['IED', 'Industrial Engineering Design', 'BSIED', 'Bachelor of Science in Industrial Engineering Design'],
        'ICS' => ['ICS', 'Information and Computer Science', 'BSICS', 'Bachelor of Science in Information and Computer Science'],
        'CS' => ['CS', 'Computer Science', 'BSCS', 'Bachelor of Science in Computer Science'],
        'IT' => ['IT', 'Information Technology', 'BSIT', 'Bachelor of Science in Information Technology'],
        'IS' => ['IS', 'Information Systems', 'BSIS', 'Bachelor of Science in Information Systems'],
        'ACT' => ['ACT', 'Computer Technology', 'Associate in Computer Technology'],
        'BCAEd' => ['BCAEd', 'BCAED', 'Culture and Arts Education', 'Bachelor of Culture and Arts Education'],
        'BECEd' => ['BECEd', 'BECED', 'Early Childhood Education', 'Bachelor of Early Childhood Education'],
        'BEEd' => ['BEEd', 'BEED', 'Elementary Education', 'Bachelor of Elementary Education'],
    ];
    
    $input = trim($programCode . ' ' . $programName);
    
    foreach ($programMapping as $standard => $variants) {
        foreach ($variants as $variant) {
            if (stripos($input, $variant) !== false) {
                return $standard;
            }
        }
    }
    
    return 'Unknown';
}

function isEducationProgram($standardProgram)
{
    return in_array($standardProgram, ['BCAEd', 'BECEd', 'BEEd'], true);
}

function buildEducFeatureVector(array $orderedAnswers)
{
    $featureNames = array_merge(
        array_map(fn($i) => "Apt_Q{$i}", range(1, 10)),
        array_map(fn($i) => "Pers_Q{$i}", range(1, 10)),
        array_map(fn($i) => "Int_Q{$i}", range(1, 10)),
    );

    $featureValues = [];
    $answerIndex = 0;

    foreach ($featureNames as $name) {
        $featureValues[$name] = $orderedAnswers[$answerIndex] ?? -1;
        $answerIndex++;
    }

    return $featureValues;
}

function applyCareerWeights($topProbs, $programCode, $programName, $weightsMap)
{
    if (empty($weightsMap)) {
        return $topProbs;
    }
    
    $standardProgram = standardizeProgramCode($programCode, $programName);
    
    if ($standardProgram === 'Unknown') {
        return $topProbs;
    }
    
    $adjustedProbs = [];
    
    foreach ($topProbs as $item) {
        $career = $item['career'];
        $originalProb = $item['prob'];
        
        $relevanceWeight = 1.0;
        
        if (isset($weightsMap[$career][$standardProgram])) {
            $relevanceScore = $weightsMap[$career][$standardProgram];
            $relevanceWeight = 0.5 + ($relevanceScore / 3.0) * 1.5;
        }
        
        $adjustedProb = $originalProb * $relevanceWeight;
        
        $adjustedProbs[] = [
            'career' => $career,
            'prob' => $originalProb,
            'adjusted_prob' => $adjustedProb,
            'relevance_weight' => $relevanceWeight,
            'relevance_score' => $weightsMap[$career][$standardProgram] ?? null
        ];
    }
    
    usort($adjustedProbs, function($a, $b) {
        return $b['adjusted_prob'] <=> $a['adjusted_prob'];
    });
    
    return array_map(function($item) {
        return [
            'career' => $item['career'],
            'prob' => $item['adjusted_prob'],
            'original_prob' => $item['prob'],
            'relevance_score' => $item['relevance_score']
        ];
    }, $adjustedProbs);
}

function logPredictionResult($data)
{
    $logFile = __DIR__ . '/../../ml/prediction_results.jsonl';

    $payload = array_merge($data, [
        'timestamp' => date('c'),
    ]);

    @file_put_contents(
        $logFile,
        json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
}

function predictCareerWithRandomForest($conn, $student_id)
{
    $allAnswers = getAllStudentAnswers($conn, $student_id);
    [$programCode, $programId, $programName, $departmentId] = getStudentProgramCode($conn, $student_id);

    $standardProgram = standardizeProgramCode($programCode, $programName);
    $isEduc = isEducationProgram($standardProgram);
    $isIed = in_array($standardProgram, ['IED', 'ICS'], true);

    $requiredAnswers = $isEduc ? 30 : 40;
    if (count($allAnswers) !== $requiredAnswers) {
        return [
            'error' => 'incomplete_assessment',
            'required' => $requiredAnswers,
            'submitted' => count($allAnswers),
        ];
    }

    $featureValues = $isEduc
        ? buildEducFeatureVector($allAnswers)
        : buildRfFeatureVector($allAnswers);

    $payload = [
        'current_program' => $programCode,
        'features' => $featureValues,
    ];

    $python = realpath(__DIR__ . '/../../env/Scripts/python.exe');
    if ($isEduc) {
        $scriptName = 'predict_career_educ.py';
    } elseif ($isIed) {
        $scriptName = 'predict_career_ied.py';
    } else {
        $scriptName = 'predict_career.py';
    }
    $script = realpath(__DIR__ . '/../../ml/' . $scriptName);

    if (!$python || !$script) {
        return ['error' => 'python_runtime_missing'];
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'rf_payload_');
    file_put_contents($tmpFile, json_encode($payload));

    $cmd = escapeshellarg($python) . ' ' .
           escapeshellarg($script) . ' --input-file ' .
           escapeshellarg($tmpFile);

    $process = proc_open($cmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    proc_close($process);
    unlink($tmpFile);

    if (!$stdout) {
        return ['error' => 'ml_runtime_failed', 'stderr' => $stderr];
    }

    $result = json_decode($stdout, true);

    if (!$result || !isset($result['prediction'])) {
        return ['error' => 'invalid_model_output'];
    }

    if ($isEduc) {
        $weightsMap = loadCareerWeightsMap(false, '_educ');
    } else {
        $weightsMap = loadCareerWeightsMap($isIed);
    }
    $topProbs = $result['top_probs'] ?? [];
    
    if (!empty($weightsMap) && !empty($topProbs)) {
        $topProbs = applyCareerWeights($topProbs, $programCode, $programName, $weightsMap);
        $result['top_probs'] = $topProbs;
        
        if (!empty($topProbs)) {
            $result['prediction'] = $topProbs[0]['career'];
        }
    }

    $allowedCareers = getCareersForProgram($conn, $programId, $departmentId);
    $topCareers = array_column($result['top_probs'] ?? [], 'career');

    if (!empty($allowedCareers)) {
        $topCareers = array_values(array_filter($topCareers, function ($c) use ($allowedCareers) {
            return in_array($c, $allowedCareers, true);
        }));
    }

    $topCareers = array_values(array_unique($topCareers));
    
    $predictedCareers = $topCareers;

    if (!empty($allowedCareers)) {
        $missingAllowed = array_values(array_diff($allowedCareers, $topCareers));
        $topCareers = array_merge($topCareers, $missingAllowed);
    }

    if (!empty($allowedCareers) && empty($topCareers)) {
        return [
            'error' => 'no_allowed_careers_match',
            'message' => 'No recommended careers match your program. Please contact the administrator.',
        ];
    }

    if ($programId && empty($allowedCareers)) {
        return [
            'error' => 'no_allowed_careers_match',
            'message' => 'No recommended careers are configured for your program. Please contact the administrator.',
        ];
    }

    $mostLikely = $result['prediction'] ?? null;
    if (!empty($allowedCareers) && !in_array($mostLikely, $allowedCareers, true)) {
        $mostLikely = $topCareers[0] ?? null;
    }

    $leastLikely = (count($topCareers) >= 2) ? end($topCareers) : null;

    $careerDescriptions = getCareerDescriptions($conn, $topCareers, $programId);
    
    $careersForResources = array_slice($predictedCareers, 0, 3);
    $careerResourcesDetailed = getCareerResources($conn, $careersForResources);
    $careerResources = getSimpleCareerResources($conn, $careersForResources);

    $response = [
        'title' => $mostLikely ?? $result['prediction'],
        'most_likely' => $mostLikely,
        'least_likely' => $leastLikely,
        'careers' => $topCareers,
        'career_descriptions' => $careerDescriptions,
        'resources' => $careerResources,
        'resources_detailed' => $careerResourcesDetailed,
        'description' => 'Random Forest recommendation based on your completed assessments.',
        'confidence' => $result['confidence'] ?? null,
        'source' => 'random_forest',
        'program_code' => $programCode,
        'program_name' => $programName,
    ];

    logPredictionResult([
        'student_id' => $student_id,
        'program_code' => $programCode,
        'prediction' => $response['title'],
        'top_careers' => $topCareers,
    ]);

    return $response;
}

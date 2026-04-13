<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require __DIR__ . '/../admin/includes/consultation_functions.php';

ensureConsultationTables($conn);
$activePage = 'consultation_calendar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Consultation Calendar</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    
    <style>
        .calendar-card {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        #calendar {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* FullCalendar Customization */
        .fc {
            font-family: inherit;
        }
        
        .fc .fc-toolbar-title {
            font-size: 1.75em;
            font-weight: 600;
            color: #333;
        }
        
        .fc .fc-button {
            background: rgb(110, 24, 42);
            border: none;
            border-radius: 8px;
            text-transform: capitalize;
            padding: 11px 16px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.2s ease;
            color: #fff;
        }
        
        .fc .fc-button:hover {
            background: rgb(90, 19, 34);
            box-shadow: 0 6px 18px rgba(110, 24, 42, 0.18);
            transform: translateY(-1px);
        }
        
        .fc .fc-button:active {
            transform: translateY(0);
        }
        
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background: rgb(90, 19, 34);
            box-shadow: 0 6px 18px rgba(110, 24, 42, 0.25);
        }
        
        .fc .fc-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .fc-daygrid-day-number {
            font-weight: 600;
            font-size: 14px;
            padding: 8px;
        }
        
        .fc .fc-daygrid-day.fc-day-today {
            background-color: #fff3cd !important;
        }
        
        .fc-event {
            border-radius: 4px;
            padding: 2px 4px;
            margin: 2px 0;
            font-size: 11px;
            cursor: pointer;
        }
        
        .fc-event.morning-slot {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .fc-event.afternoon-slot {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .fc-event.both-slots {
            background: linear-gradient(90deg, #28a745 50%, #007bff 50%);
            border: none;
        }
        
        .calendar-legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-color.morning {
            background-color: #28a745;
        }
        
        .legend-color.afternoon {
            background-color: #007bff;
        }
        
        .legend-color.both {
            background: linear-gradient(90deg, #28a745 50%, #007bff 50%);
        }
        
        .legend-color.today {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
        }
        
        .muted { 
            color: #6c757d; 
            font-size: 14px;
            text-align: center;
            margin-top: 15px;
        }
        
        .fc-popover {
            z-index: 1000;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .slot-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .slot-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .slot-item.afternoon {
            border-left-color: #007bff;
        }
        
        .slot-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 50%;
        }
        
        .slot-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
        }
        
        .slot-details p {
            margin: 0;
            font-size: 13px;
            color: #666;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-btn {
            padding: 11px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .modal-btn-primary {
            background: rgb(110, 24, 42);
            color: #fff;
        }
        
        .modal-btn-primary:hover {
            background: rgb(90, 19, 34);
            box-shadow: 0 6px 18px rgba(110, 24, 42, 0.18);
            transform: translateY(-1px);
        }
        
        .modal-btn-secondary {
            background: #e9ecef;
            color: #333;
        }
        
        .modal-btn-secondary:hover {
            background: #dee2e6;
        }
        
        @media screen and (max-width: 768px) {
            .calendar-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            #calendar {
                font-size: 12px;
            }
            
            .fc .fc-toolbar-title {
                font-size: 1.2em;
            }
            
            .fc .fc-button {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .fc .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
            
            .fc .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
            }
            
            .fc-daygrid-day-number {
                font-size: 12px;
                padding: 4px;
            }
            
            .fc-event {
                font-size: 10px;
                padding: 1px 2px;
            }
            
            /* Legend responsive */
            .calendar-legend {
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px;
            }
            
            .legend-item {
                font-size: 12px;
                flex: 1 1 45%;
                justify-content: center;
            }
            
            .legend-color {
                width: 16px;
                height: 16px;
            }
            
            /* Modal responsive */
            .modal-content {
                padding: 20px;
                max-width: 95%;
                width: 95%;
            }
            
            .modal-header h3 {
                font-size: 18px;
            }
            
            .modal-close {
                font-size: 24px;
                width: 30px;
                height: 30px;
            }
            
            #modalDate {
                font-size: 14px !important;
            }
            
            .slot-info {
                gap: 12px;
            }
            
            .slot-item {
                padding: 12px;
                gap: 10px;
            }
            
            .slot-icon {
                font-size: 20px;
                width: 35px;
                height: 35px;
            }
            
            .slot-details h4 {
                font-size: 14px;
            }
            
            .slot-details p {
                font-size: 12px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
                padding: 12px 20px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .calendar-card {
                padding: 10px;
                border-radius: 8px;
            }
            
            .fc .fc-toolbar-title {
                font-size: 1em;
            }
            
            .fc .fc-button {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .fc-daygrid-day-number {
                font-size: 11px;
                padding: 2px;
            }
            
            .fc-col-header-cell-cushion {
                padding: 4px 2px;
                font-size: 11px;
            }
            
            .fc-event {
                font-size: 9px;
                padding: 1px;
            }
            
            .legend-item {
                font-size: 11px;
                flex: 1 1 100%;
            }
            
            .modal-content {
                padding: 15px;
            }
            
            .modal-header {
                margin-bottom: 15px;
                padding-bottom: 10px;
            }
            
            .modal-header h3 {
                font-size: 16px;
            }
            
            .slot-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }
            
            .slot-icon {
                font-size: 18px;
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
<input type="checkbox" id="sidebar-toggle">

<div class="main-content">
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>
            <span class="las la-calendar"></span>
            Consultation Calendar
        </h2>
        

        <div class="calendar-card">
            <div id="calendar"></div>
            <div id="calendarMessage" class="muted"></div>
            
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-color morning"></div>
                    <span>Morning Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color afternoon"></div>
                    <span>Afternoon Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color both"></div>
                    <span>Both Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color today"></div>
                    <span>Today</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="slotModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Available Consultation Slots</h3>
            <button class="modal-close" onclick="closeSlotModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalDate" style="font-size: 16px; color: #666; margin-bottom: 20px;">
                <i class="las la-calendar"></i> <span id="dateText"></span>
            </div>
            <div class="slot-info" id="slotInfo">
            </div>
            <div id="bookingMessage" class="muted" style="margin-top: 10px; text-align: left;"></div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-secondary" onclick="closeSlotModal()">Close</button>
        </div>
    </div>
</div>

<?php 
include 'sidebar.php'; 
?>
<label for="sidebar-toggle" class="sidebar-overlay"></label>

<script>
let calendar;
const message = document.getElementById('calendarMessage');
let selectedDateStr = '';

async function loadAvailability() {
    message.textContent = 'Loading calendar...';
    try {
        const res = await fetch(`consultation_calendar_api.php`);
        const data = await res.json();
        const rows = data.data || [];
        
        if (!rows.length) {
            message.textContent = 'No availability configured yet.';
            return;
        }
        
        message.textContent = '';
        
        const events = [];
        rows.forEach(row => {
            const morningEnabled = row.morning_enabled == 1;
            const afternoonEnabled = row.afternoon_enabled == 1;
            
            if (morningEnabled && afternoonEnabled) {
                events.push({
                    title: 'Morning & Afternoon',
                    start: row.available_date,
                    allDay: true,
                    className: 'both-slots',
                    extendedProps: {
                        morning: true,
                        afternoon: true
                    }
                });
            } else if (morningEnabled) {
                events.push({
                    title: 'Morning Available',
                    start: row.available_date,
                    allDay: true,
                    className: 'morning-slot',
                    extendedProps: {
                        morning: true,
                        afternoon: false
                    }
                });
            } else if (afternoonEnabled) {
                events.push({
                    title: 'Afternoon Available',
                    start: row.available_date,
                    allDay: true,
                    className: 'afternoon-slot',
                    extendedProps: {
                        morning: false,
                        afternoon: true
                    }
                });
            }
        });
        
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            events: events,
            eventClick: function(info) {
                info.jsEvent.preventDefault(); // Prevent navigation
                
                const props = info.event.extendedProps;
                const dateStr = info.event.startStr;
                
                // Format date nicely
                const date = new Date(dateStr + 'T00:00:00');
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const formattedDate = date.toLocaleDateString('en-US', options);
                
                // Show modal with slot information
                showSlotModal(formattedDate, props);
            },
            eventContent: function(arg) {
                return {
                    html: `<div style="padding: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${arg.event.title}</div>`
                };
            },
            height: 'auto',
            firstDay: 0, // Sunday
            fixedWeekCount: false,
            showNonCurrentDates: true,
            dayMaxEvents: 3
        });
        
        calendar.render();
        
    } catch (err) {
        console.error('Calendar error:', err);
        message.textContent = 'Unable to load calendar right now.';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadAvailability();
});

function showSlotModal(date, slots) {
    const modal = document.getElementById('slotModal');
    const dateText = document.getElementById('dateText');
    const slotInfo = document.getElementById('slotInfo');
    const bookingMsg = document.getElementById('bookingMessage');
    
    dateText.textContent = date;
    selectedDateStr = date;
    
    slotInfo.innerHTML = '';
    bookingMsg.textContent = '';
    
    // Add morning slot if available
    if (slots.morning) {
        const morningSlot = document.createElement('div');
        morningSlot.className = 'slot-item';
        morningSlot.innerHTML = `
            <div class="slot-details">
                <h4>Morning Session</h4>
                <p>Consultation available in the morning</p>
            </div>
            <button class="modal-btn modal-btn-primary" onclick="bookConsultation('morning')">
                Book Morning
            </button>
        `;
        slotInfo.appendChild(morningSlot);
    }
    
    if (slots.afternoon) {
        const afternoonSlot = document.createElement('div');
        afternoonSlot.className = 'slot-item afternoon';
        afternoonSlot.innerHTML = `
          
            <div class="slot-details">
                <h4>Afternoon Session</h4>
                <p>Consultation available in the afternoon</p>
            </div>
            <button class="modal-btn modal-btn-primary" onclick="bookConsultation('afternoon')">
                Book Afternoon
            </button>
        `;
        slotInfo.appendChild(afternoonSlot);
    }
    
    modal.classList.add('show');
}

function closeSlotModal() {
    const modal = document.getElementById('slotModal');
    modal.classList.remove('show');
}

async function bookConsultation(session) {
    const bookingMsg = document.getElementById('bookingMessage');
    bookingMsg.textContent = 'Booking...';
    try {
        const res = await fetch('consultation_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ session })
        });

        const data = await res.json();
        if (res.ok && data.success) {
            bookingMsg.textContent = `Booked ${data.session} on ${data.scheduled_date}.`;
        } else {
            bookingMsg.textContent = data.message || 'Could not book this slot.';
        }
    } catch (err) {
        bookingMsg.textContent = 'Something went wrong. Please try again.';
    }
}

document.getElementById('slotModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSlotModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSlotModal();
    }
});
</script>
</body>
</html>


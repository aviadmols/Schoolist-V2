  (function () {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initClassroomPage);
    } else {
      initClassroomPage();
    }

    function initClassroomPage() {
    // Wait for DOM to be ready and popups to be rendered
    if (document.readyState === 'complete') {
      setupClassroomPageFeatures();
    } else {
      window.addEventListener('load', () => {
        setTimeout(setupClassroomPageFeatures, 200);
      });
    }
    }

    function setupClassroomPageFeatures() {
      try {
        // Quick Add Logic
        const quickAddTrigger = document.getElementById('ai-quick-add-trigger');
        const quickAddPopup = document.getElementById('popup-quick-add');
        const quickAddText = document.getElementById('quick-add-text');
        const quickAddFile = document.getElementById('quick-add-file');
        const quickAddFilePreview = document.getElementById('quick-add-file-preview');
        const quickAddFileName = document.getElementById('file-name');
        const removeFileBtn = document.getElementById('remove-file');
        const quickAddSubmit = document.getElementById('quick-add-submit');
        const quickAddIsPublic = document.getElementById('quick-add-is-public');
        const aiConfirmPopup = document.getElementById('popup-ai-confirm');
        const aiConfirmSave = document.getElementById('ai-confirm-save');
        const aiSuggestionContent = document.getElementById('ai-suggestion-content');
        
        let selectedFile = null;
        let aiSuggestionData = null;
        const classroomId = {{ ($page['classroom']['id'] ?? $classroom->id ?? null) ?: 'null' }};
        const weekDates = {!! json_encode($page['week_dates'] ?? []) !!};

        // Open quick add popup
        if (quickAddTrigger && quickAddPopup) {
          quickAddTrigger.addEventListener('click', () => {
            quickAddPopup.classList.add('is-open');
            const backdrop = document.querySelector('[data-popup-backdrop]');
            if (backdrop) backdrop.classList.add('is-open');
          });
        }

        // File selection
        if (quickAddFile && quickAddFilePreview && quickAddFileName) {
          quickAddFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
              selectedFile = file;
              quickAddFileName.textContent = file.name;
              quickAddFilePreview.style.display = 'block';
            }
          });
        }

        // Remove file
        if (removeFileBtn && quickAddFile && quickAddFilePreview) {
          removeFileBtn.addEventListener('click', () => {
            selectedFile = null;
            quickAddFile.value = '';
            quickAddFilePreview.style.display = 'none';
          });
        }

        // Submit quick add - send to AI
        if (quickAddSubmit && quickAddText) {
          quickAddSubmit.addEventListener('click', async () => {
            // Validate classroomId
            if (!classroomId || classroomId === 'null') {
              alert('שגיאה: לא נמצא מזהה כיתה');
              return;
            }

            const text = quickAddText.value.trim();
            if (!text && !selectedFile) {
              alert('אנא הזן טקסט או בחר קובץ');
              return;
            }

            quickAddSubmit.disabled = true;
            quickAddSubmit.textContent = 'מנתח...';

            try {
              const formData = new FormData();
              formData.append('content_text', text || '');
              if (selectedFile) {
                formData.append('content_file', selectedFile);
              }
              var activeTab = document.querySelector('.day-tab.active');
              var dayIndex = activeTab ? parseInt(activeTab.getAttribute('data-day') || '0', 10) : 0;
              var sendDate = Array.isArray(weekDates) && weekDates[dayIndex] ? weekDates[dayIndex] : '';
              var sendDayName = (dayNames && dayNames[dayIndex]) ? dayNames[dayIndex] : '';
              if (sendDate) formData.append('target_date', sendDate);
              if (sendDayName) formData.append('target_day_name', sendDayName);

              console.log('[AI Quick Add] Sending request', {
                hasText: !!text,
                textLength: text.length,
                hasFile: !!selectedFile,
                fileName: selectedFile?.name,
                fileSize: selectedFile?.size,
              });

              const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
              if (!csrfToken) {
                throw new Error('CSRF token לא נמצא');
              }

              const response = await fetch(`/class/${classroomId}/ai-analyze`, {
                method: 'POST',
                headers: {
                  'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
              });

              console.log('[AI Quick Add] Response status', response.status);

              // Read response text once - can only be called once
              const responseText = await response.text();
              console.log('[AI Quick Add] Raw response text', responseText.substring(0, 500));

              if (!response.ok) {
                let errorData = {};
                try {
                  errorData = JSON.parse(responseText);
                } catch (e) {
                  // If JSON parsing fails, use the text as error
                  errorData = { error: responseText || `שגיאה ${response.status}: ${response.statusText}` };
                }
                
                console.error('[AI Quick Add] Error response', {
                  status: response.status,
                  statusText: response.statusText,
                  errorData: errorData,
                  responseText: responseText.substring(0, 1000)
                });
                
                // Build detailed error message
                let errorMessage = errorData.error || errorData.message || `שגיאה ${response.status}: ${response.statusText}`;
                let errorDetails = '';
                
                if (errorData.details) {
                  errorDetails = '\n\nפרטים נוספים:\n' + errorData.details;
                }
                
                if (errorData.errors) {
                  const errorsList = Object.entries(errorData.errors)
                    .map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(', ') : value}`)
                    .join(' | ');
                  errorDetails += '\n\nשגיאות אימות:\n' + errorsList;
                }
                
                if (response.status === 500 && !errorData.error) {
                  errorMessage += ' | שגיאת שרת פנימית - אנא נסה שוב או פנה לתמיכה';
                }
                
                throw new Error(errorMessage + errorDetails);
              }

              let result;
              try {
                result = JSON.parse(responseText);
                console.log('[AI Quick Add] Response data', result);
              } catch (jsonError) {
                console.error('[AI Quick Add] Failed to parse JSON response', jsonError);
                console.error('[AI Quick Add] Response text', responseText.substring(0, 500));
                throw new Error('תגובה לא תקינה מהשרת: ' + (responseText.substring(0, 200) || jsonError.message));
              }
              
              // Check if response is ok
              if (!result || result.ok === false) {
                const errorMsg = result?.error || result?.message || 'לא התקבלה הצעה מה-AI';
                let errorDetails = '';
                if (result?.details) {
                  errorDetails = '\n\nפרטים נוספים:\n' + result.details;
                }
                if (result?.errors) {
                  errorDetails += '\n\nשגיאות אימות:\n' + JSON.stringify(result.errors, null, 2);
                }
                console.error('[AI Quick Add] Response not ok', { result, errorMsg, errorDetails });
                throw new Error(errorMsg + errorDetails);
              }
              
              if (!result.suggestion) {
                console.error('[AI Quick Add] No suggestion in response', result);
                const responsePreview = JSON.stringify(result).substring(0, 300);
                throw new Error('לא התקבלה הצעה מה-AI - התגובה לא מכילה הצעה. תגובה: ' + responsePreview);
              }

              const suggestion = result.suggestion;
              aiSuggestionData = suggestion;
              
              // Show AI suggestion popup
              if (aiSuggestionContent) {
                const extractedData = suggestion.extracted_data || {};
                const type = suggestion.type || 'announcement';
                const typeLabel = type === 'event' ? 'אירוע' : type === 'homework' ? 'שיעורי בית' : 'הודעה';
                
                // Handle multiple items or single item
                const items = extractedData.items || [extractedData];
                const firstItem = items[0] || {};
                
                aiSuggestionContent.innerHTML = `
                  <div style="margin-bottom: 16px;">
                    <strong>סוג:</strong> ${typeLabel}<br>
                    <strong>כותרת:</strong> ${firstItem.title || firstItem.name || ''}<br>
                    <strong>תוכן:</strong> ${firstItem.content || firstItem.description || ''}<br>
                    ${firstItem.date || firstItem.due_date ? `<strong>תאריך:</strong> ${firstItem.date || firstItem.due_date}<br>` : ''}
                    ${firstItem.time ? `<strong>שעה:</strong> ${firstItem.time}<br>` : ''}
                    ${firstItem.location ? `<strong>מיקום:</strong> ${firstItem.location}<br>` : ''}
                  </div>
                `;
              }

              // Close quick add popup
              if (quickAddPopup) {
                quickAddPopup.classList.remove('is-open');
              }
              const backdrop = document.querySelector('[data-popup-backdrop]');
              if (backdrop) backdrop.classList.remove('is-open');

              // Open AI confirm popup
              if (aiConfirmPopup) {
                aiConfirmPopup.classList.add('is-open');
                if (backdrop) backdrop.classList.add('is-open');
              }

            } catch (error) {
              console.error('[AI Quick Add] Error analyzing:', error);
              console.error('[AI Quick Add] Error stack:', error.stack);
              console.error('[AI Quick Add] Error details:', {
                name: error.name,
                message: error.message,
                cause: error.cause,
                error: error
              });
              
              let errorMessage = 'שגיאה לא ידועה';
              let errorDetails = '';
              
              if (error instanceof Error) {
                errorMessage = error.message || error.toString();
                // Extract details from error message if it contains newlines
                if (errorMessage.includes('\n\nפרטים נוספים:')) {
                  const parts = errorMessage.split('\n\nפרטים נוספים:');
                  errorMessage = parts[0];
                  errorDetails = '\n\nפרטים נוספים:' + parts[1];
                }
                if (error.stack && !errorDetails) {
                  errorDetails = '\n\nפרטים טכניים:\n' + error.stack.split('\n').slice(0, 5).join('\n');
                }
              } else if (typeof error === 'string') {
                errorMessage = error;
              } else if (error && error.message) {
                errorMessage = error.message;
              } else {
                errorMessage = JSON.stringify(error).substring(0, 500);
              }
              
              // Build full error message with clear formatting
              let fullErrorMessage = errorMessage;
              if (errorDetails) {
                fullErrorMessage += errorDetails;
              }
              
              // Show error in alert with better formatting
              console.error('[AI Quick Add] Full error message:', fullErrorMessage);
              
              // Don't duplicate "שגיאה בניתוח" prefix if it's already in the message
              if (fullErrorMessage.includes('שגיאה בניתוח')) {
                alert(fullErrorMessage);
              } else {
                alert('שגיאה בניתוח:\n\n' + fullErrorMessage);
              }
            } finally {
              quickAddSubmit.disabled = false;
              quickAddSubmit.textContent = 'המשך';
            }
          });
        }

        // Save AI suggestion
        if (aiConfirmSave) {
          aiConfirmSave.addEventListener('click', async () => {
            if (!aiSuggestionData) return;

            // Validate classroomId
            if (!classroomId || classroomId === 'null') {
              alert('שגיאה: לא נמצא מזהה כיתה');
              return;
            }

            aiConfirmSave.disabled = true;
            aiConfirmSave.textContent = 'שומר...';

            try {
              const isPublic = quickAddIsPublic ? quickAddIsPublic.checked : false;
              
              const response = await fetch(`/class/${classroomId}/ai-store`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                  suggestion: aiSuggestionData,
                  is_public: isPublic,
                }),
              });

              if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'שגיאה בשמירה');
              }

              // Close popups and reset form
              if (aiConfirmPopup) {
                aiConfirmPopup.classList.remove('is-open');
              }
              const backdrop = document.querySelector('[data-popup-backdrop]');
              if (backdrop) backdrop.classList.remove('is-open');

              if (quickAddText) quickAddText.value = '';
              if (quickAddFile) quickAddFile.value = '';
              if (quickAddFilePreview) quickAddFilePreview.style.display = 'none';
              selectedFile = null;
              aiSuggestionData = null;

              // Reload page to show new content
              window.location.reload();

            } catch (error) {
              console.error('Error saving:', error);
              alert('שגיאה בשמירה: ' + (error.message || 'שגיאה לא ידועה'));
            } finally {
              aiConfirmSave.disabled = false;
              aiConfirmSave.textContent = 'אשר ושמור';
            }
          });
        }
      } catch (err) {
        console.error('Error in setupClassroomPageFeatures:', err);
      }

      // Day selection and schedule rendering
      const dayNames = Array.from(document.querySelectorAll('.day-tab'))
        .map((tab) => (tab && tab.textContent) ? tab.textContent.trim() : '');
      const timetable = {!! json_encode($page['timetable'] ?? []) !!};
      const selectedDayNameEl = document.getElementById('selected-day-name');
      const scheduleEl = document.getElementById('schedule-content');

      const buildScheduleHtml = (entries) => {
      if (!Array.isArray(entries) || entries.length === 0) {
        return '<div class="schedule-row"><span class="schedule-subject">---</span><span class="schedule-time">08:00-09:00</span></div>';
      }

      return entries.map((entry) => {
        const subject = entry?.subject || '';
        const startTime = entry?.start_time || '';
        const endTime = entry?.end_time || '';
        const time = startTime || endTime ? `${startTime}-${endTime}` : '';
        return `<div class="schedule-row"><span class="schedule-subject">${subject}</span><span class="schedule-time">${time}</span></div>`;
      }).join('');
    };

      const renderSchedule = (dayIndex) => {
        if (!scheduleEl) return;
        const name = dayNames[dayIndex] || '';
        if (selectedDayNameEl) {
          selectedDayNameEl.textContent = name;
        }
        scheduleEl.innerHTML = buildScheduleHtml(timetable[dayIndex] || []);
      };

      document.querySelectorAll('.day-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.day-tab').forEach((item) => item.classList.remove('active'));
        tab.classList.add('active');
        const dayIndex = parseInt(tab.getAttribute('data-day') || '0', 10);
        renderSchedule(dayIndex);
      });
      });

      const initialTab = document.querySelector('.day-tab.active');
      if (initialTab) {
        const dayIndex = parseInt(initialTab.getAttribute('data-day') || '0', 10);
        renderSchedule(dayIndex);
      }

      const list = document.getElementById('draggable-list');
      let draggingItem = null;

      const getDragAfterElement = (container, y) => {
      const draggableElements = [...container.querySelectorAll('.link-card:not([style*="opacity: 0.5"])')];
        return draggableElements.reduce((closest, child) => {
          const box = child.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
          }
          return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
      };

      if (list) {
      list.addEventListener('dragstart', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        draggingItem = target;
        target.style.opacity = '0.5';
      });

      list.addEventListener('dragend', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        target.style.opacity = '1';
        draggingItem = null;
      });

      list.addEventListener('dragover', (event) => {
        event.preventDefault();
        if (!draggingItem) return;
        const afterElement = getDragAfterElement(list, event.clientY);
        if (!afterElement) {
          list.appendChild(draggingItem);
        } else {
          list.insertBefore(draggingItem, afterElement);
        }
        });
      }

      // Safely get popup content elements
      const contentPopupTitle = document.getElementById('popup-content-title');
      const contentPopupType = document.getElementById('popup-content-type');
      const contentPopupBody = document.getElementById('popup-content-body');
      const contentPopupDate = document.getElementById('popup-content-date');
      const contentPopupTime = document.getElementById('popup-content-time');
      const contentPopupLocation = document.getElementById('popup-content-location');
      
      // Log if popup elements are missing (for debugging)
      if (!contentPopupTitle || !contentPopupType || !contentPopupBody) {
        console.warn('Some popup content elements are missing. Popup may not work correctly.');
      }
      const typeLabels = {
        message: 'הודעה',
        event: 'אירוע',
        homework: 'שיעורי בית',
      };

      const setContentPopup = (dataset) => {
        if (!dataset || typeof dataset !== 'object') return;
        try {
          const type = (dataset.itemType != null ? dataset.itemType : '') || 'message';
          if (contentPopupType) {
            contentPopupType.textContent = typeLabels[type] || type;
          }
          if (contentPopupTitle) {
            contentPopupTitle.textContent = dataset.itemTitle || '';
          }
          if (contentPopupBody) {
            contentPopupBody.textContent = dataset.itemContent || '';
          }
          if (contentPopupDate) {
            contentPopupDate.textContent = dataset.itemDate || '';
          }
          if (contentPopupTime) {
            contentPopupTime.textContent = dataset.itemTime || '';
          }
          if (contentPopupLocation) {
            contentPopupLocation.textContent = dataset.itemLocation || '';
          }
        } catch (err) {
          // Silently fail
        }
      };

      const backdrop = document.querySelector('[data-popup-backdrop]');
      const popups = document.querySelectorAll('[data-popup]');
      
      // Log popup count for debugging
      if (popups.length === 0) {
        console.warn('No popups found in DOM. Popups may not be rendered correctly.');
      } else {
        console.log(`Found ${popups.length} popups in DOM.`);
      }

      const closePopups = () => {
        popups.forEach((popup) => popup.classList.remove('is-open'));
        backdrop?.classList.remove('is-open');
      };

      const openPopup = (popupId) => {
        try {
          if (!popupId) {
            console.warn('openPopup: popupId is empty');
            return;
          }
          const target = document.getElementById(popupId);
          if (!target) {
            console.warn('Popup not found:', popupId, 'Available popups:', Array.from(document.querySelectorAll('[data-popup]')).filter(Boolean).map(p => p.id));
            return;
          }
          if (popups && popups.length > 0) {
            popups.forEach((popup) => {
              if (popup) popup.classList.remove('is-open');
            });
          }
          target.classList.add('is-open');
          if (backdrop) {
            backdrop.classList.add('is-open');
          }
        } catch (err) {
          console.error('Error opening popup:', err, 'popupId:', popupId);
        }
      };

      // Setup popup triggers with error handling
      try {
        const itemPopupTriggers = document.querySelectorAll('[data-item-popup]');
        if (itemPopupTriggers && itemPopupTriggers.length > 0) {
          itemPopupTriggers.forEach((trigger) => {
            if (!trigger || typeof trigger.addEventListener !== 'function') return;
            try {
              trigger.addEventListener('click', (event) => {
                try {
                  event.preventDefault();
                  event.stopPropagation();
                  const currentTarget = event.currentTarget && event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
                  if (!currentTarget) return;
                  const targetId = currentTarget.getAttribute('data-item-popup');
                  if (!targetId) return;
                  let dataset = {};
                  try {
                    if (currentTarget.dataset != null && typeof currentTarget.dataset === 'object') {
                      dataset = currentTarget.dataset;
                    }
                  } catch (e) {
                    // Ignore
                  }
                  setContentPopup(dataset);
                  openPopup(targetId);
                } catch (err) {
                  // Ignore
                }
              });
            } catch (err) {
              console.error('Error adding event listener to trigger:', err);
            }
          });
        }
      } catch (err) {
        console.error('Error setting up item popup triggers:', err);
      }

      try {
        const popupTargetTriggers = document.querySelectorAll('[data-popup-target]');
        if (popupTargetTriggers && popupTargetTriggers.length > 0) {
          popupTargetTriggers.forEach((trigger) => {
          if (!trigger || typeof trigger.addEventListener !== 'function') return;
          try {
            trigger.addEventListener('click', (event) => {
              try {
                event.preventDefault();
                event.stopPropagation();
                if (!trigger) return;
                const target = trigger.getAttribute('data-popup-target');
                if (target) {
                  openPopup(target);
                }
              } catch (err) {
                console.error('Error handling popup target click:', err);
              }
            });
          } catch (err) {
            console.error('Error adding event listener to popup target:', err);
          }
          });
        }
      } catch (err) {
        console.error('Error setting up popup target triggers:', err);
      }

      try {
        const closeButtons = document.querySelectorAll('[data-popup-close]');
      if (closeButtons && closeButtons.length > 0) {
        closeButtons.forEach((button) => {
          if (!button || typeof button.addEventListener !== 'function') return;
          button.addEventListener('click', (event) => {
            event.preventDefault();
            closePopups();
          });
          });
        }
      } catch (err) {
        console.error('Error setting up close buttons:', err);
      }

      if (backdrop && typeof backdrop.addEventListener === 'function') {
        backdrop.addEventListener('click', closePopups);
      }

      // Close popup-children when clicking anywhere on the popup (not only on X/buttons)
      const popupChildren = document.getElementById('popup-children');
      if (popupChildren && typeof popupChildren.addEventListener === 'function') {
        popupChildren.addEventListener('click', closePopups);
      }

      // Handle child contacts toggle
      document.querySelectorAll('.child-name').forEach((nameEl) => {
      if (!nameEl) return;
      nameEl.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!nameEl) return;
        const childRow = nameEl.closest('.child-row');
        if (!childRow) return;
        const childId = childRow.getAttribute('data-child-id');
        if (!childId) return;
        const contactsEl = document.querySelector(`.child-contacts[data-child-id="${childId}"]`);
        if (contactsEl) {
          contactsEl.style.display = contactsEl.style.display === 'none' ? 'block' : 'none';
          }
        });
      });

      // Handle announcement toggle
      document.querySelectorAll('.notice-check').forEach((checkEl) => {
      if (!checkEl) return;
      checkEl.addEventListener('click', async (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!checkEl) return;
        const noticeRow = checkEl.closest('.notice-row');
        if (!noticeRow) return;
        const announcementId = noticeRow.getAttribute('data-announcement-id');
        if (!announcementId) return;

        const isDone = noticeRow.classList.contains('notice-done');
        
        try {
          const response = await fetch(`/announcements/${announcementId}/done`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
          });

          if (response.ok) {
            if (!isDone) {
              noticeRow.classList.add('notice-done');
              noticeRow.setAttribute('data-is-done', '1');
              // Confetti effect
              createConfetti();
            } else {
              noticeRow.classList.remove('notice-done');
              noticeRow.setAttribute('data-is-done', '0');
            }
          }
        } catch (error) {
          console.error('Failed to toggle announcement:', error);
          }
        });
      });

      // Handle add to calendar
      document.querySelectorAll('.add-to-calendar-btn').forEach((btn) => {
      if (!btn) return;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!btn) return;
        const date = btn.getAttribute('data-event-date') || '';
        const time = btn.getAttribute('data-event-time') || '';
        const title = btn.getAttribute('data-event-title') || '';
        const location = btn.getAttribute('data-event-location') || '';

        if (!date) return;

        const [day, month, year] = date.split('.');
        const [hours, minutes] = time ? time.split(':') : ['12', '00'];
        const startDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hours), parseInt(minutes));
        const endDate = new Date(startDate.getTime() + 60 * 60 * 1000);

        const formatICSDate = (date) => {
          return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        };

        const icsContent = [
          'BEGIN:VCALENDAR',
          'VERSION:2.0',
          'PRODID:-//Schoolist//Classroom Events//EN',
          'BEGIN:VEVENT',
          `DTSTART:${formatICSDate(startDate)}`,
          `DTEND:${formatICSDate(endDate)}`,
          `SUMMARY:${title}`,
          location ? `LOCATION:${location}` : '',
          'END:VEVENT',
          'END:VCALENDAR',
        ].filter(Boolean).join('\r\n');

        const blob = new Blob([icsContent], { type: 'text/calendar' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${title.replace(/[^a-z0-9]/gi, '_')}.ics`;
        document.body.appendChild(link);
        link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
        });
      });

      // Confetti effect function
      function createConfetti() {
      const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#6c5ce7'];
      const confettiCount = 50;
      
      for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.style.position = 'fixed';
        confetti.style.width = '8px';
        confetti.style.height = '8px';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.top = '-10px';
        confetti.style.borderRadius = '50%';
        confetti.style.pointerEvents = 'none';
        confetti.style.zIndex = '9999';
        confetti.style.opacity = '0.9';
        
        document.body.appendChild(confetti);
        
        const animation = confetti.animate([
          { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
          { transform: `translateY(${window.innerHeight + 100}px) rotate(720deg)`, opacity: 0 }
        ], {
          duration: 2000 + Math.random() * 1000,
          easing: 'cubic-bezier(0.5, 0, 0.5, 1)',
        });
        
          animation.onfinish = () => confetti.remove();
        }
      }
    } // End of setupClassroomPageFeatures
  })();

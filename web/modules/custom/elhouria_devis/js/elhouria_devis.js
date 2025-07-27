(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.elhouriaDevis = {
    attach: function (context, settings) {
      // Gestion du formulaire utilisateur
      const submitBtn = document.getElementById('submit-btn');
      const form = document.getElementById('user-info-form');

      if (submitBtn && form) {
        submitBtn.addEventListener('click', function(e) {
          e.preventDefault();

          // Récupérer les valeurs des champs
          const nom = document.getElementById('nom').value.trim();
          const prenom = document.getElementById('prenom').value.trim();
          const email = document.getElementById('email').value.trim();
          const telephone = document.getElementById('telephone').value.trim();

          // Validation des champs
          if (!nom || !prenom || !email || !telephone) {
            Swal.fire({
              icon: 'error',
              title: 'Erreur de validation',
              text: 'Tous les champs obligatoires doivent être remplis.',
              confirmButtonColor: '#d33',
              confirmButtonText: 'Compris',
              customClass: {
                popup: 'swal-popup-custom'
              }
            });
            return;
          }

          // Validation email
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(email)) {
            Swal.fire({
              icon: 'error',
              title: 'Email invalide',
              text: 'Veuillez saisir une adresse email valide.',
              confirmButtonColor: '#d33',
              confirmButtonText: 'Corriger',
              customClass: {
                popup: 'swal-popup-custom'
              }
            });
            return;
          }

          // Afficher le loading avec animation
          Swal.fire({
            title: 'Mise à jour en cours...',
            html: '<div class="swal-loading-content">Veuillez patienter pendant que nous sauvegardons vos informations.</div>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            customClass: {
              popup: 'swal-popup-loading'
            },
            didOpen: () => {
              Swal.showLoading();
            }
          });

          // Préparer les données pour l'envoi
          const formData = new FormData();
          formData.append('nom', nom);
          formData.append('prenom', prenom);
          formData.append('email', email);
          formData.append('telephone', telephone);

          // Envoi AJAX
          fetch('/user/submit', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(response => {
            if (response.ok) {
              return response.json().catch(() => ({ success: true }));
            } else {
              return response.json().then(data => Promise.reject(data));
            }
          })
          .then(data => {
            Swal.fire({
              icon: 'success',
              title: 'Parfait !',
              text: 'Vos informations ont été mises à jour avec succès.',
              confirmButtonColor: '#28a745',
              confirmButtonText: 'Excellent',
              customClass: {
                popup: 'swal-popup-success'
              },
              showClass: {
                popup: 'animate__animated animate__fadeInDown'
              },
              hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
              }
            }).then(() => {
              // Optionnel : effet de mise à jour visuelle
              document.querySelectorAll('.colFormInfos input').forEach(input => {
                input.style.borderColor = '#28a745';
                setTimeout(() => {
                  input.style.borderColor = '';
                }, 2000);
              });
            });
          })
          .catch(error => {
            console.error('Erreur:', error);
            const errorMessage = error.error || 'Une erreur inattendue s\'est produite. Veuillez réessayer.';
            Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: errorMessage,
              confirmButtonColor: '#d33',
              confirmButtonText: 'Réessayer',
              customClass: {
                popup: 'swal-popup-error'
              },
              showClass: {
                popup: 'animate__animated animate__shakeX'
              }
            });
          });
        });
      }
    }
  };

})(jQuery, Drupal);

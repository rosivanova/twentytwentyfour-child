document.addEventListener("DOMContentLoaded", function () {
  const today = new Date().toISOString().split("T")[0];

  // Reservation form Vue app
  if (document.getElementById("reservation-app")) {
    new Vue({
      el: "#reservation-app",
      data: {
        name: "",
        phone: "",
        email: "",
        arrival: "",
        leaving: "",
        roomType: "Double", // default room type

        // default value
        error: "",
        submitted: false,
        today: today,
      },
      methods: {
        submitForm() {
          if (
            this.arrival < this.today ||
            this.leaving < this.today ||
            this.leaving < this.arrival
          ) {
            this.error = "Dates must be in the future and valid.";
            return;
          }
           const phonePattern = /^\+?[0-9\s\-]{7,15}$/;
            if (!this.phone || this.phone.length < 13 || !phonePattern.test(this.phone)) {
              this.error = "Please enter a valid phone number with the country code +359881234567./ Моля въведете валиден телефонен номер с национален код";
              return;
            }
          fetch(`${reservationData.root}reservation/v1/submit`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": reservationData.nonce,
            },
            body: JSON.stringify({
              name: this.name,
              phone: this.phone,
              email: this.email,
              arrival: this.arrival,
              leaving: this.leaving,
              room_type: this.roomType,
            }),
          })
            .then((res) => {
              if (!res.ok) return res.json().then((err) => Promise.reject(err));
              return res.json();
            })
            .then(() => {
              this.submitted = true;
              this.error = "";
              this.name =
                this.phone =
                this.email =
                this.arrival =
                this.leaving =
                  "";
            })
            .catch((err) => {
              this.error = err.message || "Submission failed.";
            });
        },
      },
      template: `
        <div id="bookingholder" class="container pt-3 bg-grey">
          <h3 class="mb-4" style="color: #048a94; padding-left:15px;">Запазете стая/Book a Room</h3>
          <form @submit.prevent="submitForm" v-if="!submitted" class="needs-validation">
          <div class="form-row">
            <div class="form-group col-md-6 col-xs-12">
              <label class="form-label">Вашето име/Your name</label>
              <input v-model="name" class="form-control" required>
            </div>
            <div class="form-group col-md-6 col-xs-12">
              <label class="form-label">Телефонен номер/Telephone</label>
              <input type="tel" v-model="phone" class="form-control" placeholder="+359 88 123 4567"
    pattern="^\+?[0-9\s\-]{7,15}$" required>
            </div>
            </div>
            <div class="form-row">
            <div class="form-group col-md-6 col-xs-12">
              <label class="form-label">Email</label>
              <input v-model="email" type="email" class="form-control" required>
            </div>
            <div class="form-group col-md-6 col-xs-12">
              <label class="form-label">Тип стая/Room Type</label>
              <select v-model="roomType" class="form-control" required>
                <option value="Double">Стая за двама/Room for two</option>
                <option value="Triple">Стая за трима/Room for three</option>
                <option value="Quadruple ">Стая за четирима/Room for four</option>
              </select>
            </div>
            </div>
            <div class="form-row">
                <div class="form-group col-xl-6 col-lg-6 col-md-12 col-sm-12 col-xs-12">
                    <label class="form-label">Arrival Date</label>
                    <input type="date" v-model="arrival" :min="today" class="form-control" required>
                </div>
                <div class="form-group col-xl-6 col-lg-6 col-md-12 col-sm-12 col-xs-12">
                    <label class="form-label">Leaving Date</label>
                    <input type="date" v-model="leaving" :min="arrival || today" class="form-control" required>
                </div>
            </div>
            <div class="form-row my-3">
                <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                 <button type="submit" class="btn btn-green">Reserve</button>
                </div>
            </div>
            <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
          </form>
          <div v-else class="alert alert-success">Reservation submitted! Check your email for confirmation./Вашата резервация е изпратена. Проверете електронната си поща за потвърждение.</div>
        </div>
      `,
    });
  }

  // Reservation status check Vue app
  if (document.getElementById("reservation-status-app")) {
    new Vue({
      el: "#reservation-status-app",
      data: {
        email: "",
        arrival: "",
        statusMessage: "",
        error: "",
        today: today,
      },
      methods: {
        checkStatus() {
          if (this.arrival < this.today) {
            this.error = "Arrival date must be today or in the future.";
            return;
          }

          fetch(
            `${
              reservationData.root
            }reservation/v1/check?email=${encodeURIComponent(
              this.email
            )}&arrival=${encodeURIComponent(this.arrival)}`
          )
            .then((res) =>
              res.ok
                ? res.json()
                : res.json().then((err) => Promise.reject(err))
            )
            .then((data) => {
              this.statusMessage = `Your reservation status is: ${data.status}`;
              this.error = "";
            })
            .catch((err) => {
              this.error = err.message || "Could not find reservation.";
            });
        },
      },
      template: `
                <div class="mt-5">
                    <h3 class="mb-4">Check Your Reservation</h3>
                    <form @submit.prevent="checkStatus" class="needs-validation">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="email" type="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Arrival Date</label>
                            <input type="date" v-model="arrival" :min="today" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-info">Check Status</button>
                    </form>
                    <div v-if="statusMessage" class="alert alert-success mt-3">{{ statusMessage }}</div>
                    <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
                </div>
            `,
    });
  }
});

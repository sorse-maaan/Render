<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8" />
<title>Оплата</title>
</head>
<body>

<h2>Оплата участі</h2>

<form id="paymentForm">
    <label>Назва команди:</label><br>
    <input type="text" name="team_name" value="TestTeam" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="sorse1000@gmail.com" required><br><br>

    <button type="submit">Перейти до оплати</button>
</form>

<script>
document.getElementById("paymentForm").addEventListener("submit", async function(e){
    e.preventDefault();

    const formData = new FormData(e.target);

    const res = await fetch("checkout.php", {
        method: "POST",
        body: formData
    });

    const json = await res.json();

    if (json.url) {
        window.location = json.url; // 🔥 перенаправлення в Stripe Checkout
    } else {
        alert("Помилка: " + JSON.stringify(json));
    }
});
</script>

</body>
</html>

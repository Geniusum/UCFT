<?php

header('Content-Type: application/json');

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "ucft";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

$conn->set_charset("utf8");

$response = ["error" => null];
$request = json_decode($_GET["r"], true);
$response["received"] = $request;

$command = $request["command"] ?? null;

if (!$command) {
    $response["error"] = "No command in the request.";
    echo json_encode($response);
    exit;
}

switch ($command) {
    case "create_flow":
        $pubid = $request["pubid"] ?? null;
        $channels = $request["channels"] ?? null;
        $rate = $request["rate"] ?? "not";

        if (!$pubid || !$channels || $rate == "not") {
            $response["error"] = "Missing required fields for create_flow.";
            break;
        }

        $stmt = $conn->prepare("SELECT pubid FROM flows WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $response["error"] = "Flow with pubid already exists.";
            $stmt->close();
            break;
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO flows (pubid, channels, rate) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $pubid, $channels, $rate);
        if ($stmt->execute()) {
            $response["message"] = "Flow created successfully.";
        } else {
            $response["error"] = "Failed to create flow: " . $conn->error;
        }
        $stmt->close();
        break;

    case "delete_flow":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for delete_flow.";
            break;
        }

        $stmt = $conn->prepare("DELETE FROM flows WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response["message"] = "Flow deleted successfully.";
        } else {
            $response["error"] = "No flow found with pubid or deletion failed.";
        }
        $stmt->close();
        break;

    case "change_flow":
        $pubid = $request["pubid"] ?? null;
        $channels = $request["channels"] ?? null;
        if (!$pubid || !$channels) {
            $response["error"] = "Missing required fields for change_flow.";
            break;
        }

        $stmt = $conn->prepare("UPDATE flows SET channels = ? WHERE pubid = ?");
        $stmt->bind_param("ss", $channels, $pubid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response["message"] = "Flow updated successfully.";
        } else {
            // $response["error"] = "No flow found with pubid or update failed.";
        }
        $stmt->close();
        break;

    case "create_channel":
        $pubid = $request["pubid"] ?? null;
        $relpath = $request["relpath"] ?? null;
        $checksum = $request["checksum"] ?? null;
        $diffusion_byte = $request["diffusion_byte"] ?? null;

        if (!$pubid || !$relpath || !$checksum || !$diffusion_byte) {
            $response["error"] = "Missing required fields for create_channel.";
            break;
        }

        $stmt = $conn->prepare("SELECT pubid FROM channels WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $response["error"] = "Channel with pubid already exists.";
            $stmt->close();
            break;
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO channels (pubid, relpath, checksum, diffusion_byte) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $pubid, $relpath, $checksum, $diffusion_byte);
        if ($stmt->execute()) {
            $response["message"] = "Channel created successfully.";
        } else {
            $response["error"] = "Failed to create channel: " . $conn->error;
        }
        $stmt->close();
        break;

    case "delete_channel":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for delete_channel.";
            break;
        }

        $stmt = $conn->prepare("DELETE FROM channels WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response["message"] = "Channel deleted successfully.";
        } else {
            $response["error"] = "No channel found with pubid or deletion failed.";
        }
        $stmt->close();
        break;

    case "change_channel":
        $pubid = $request["pubid"] ?? null;
        $diffusion_byte = $request["diffusion_byte"] ?? "not";
        if (!$pubid || $diffusion_byte == "not") {
            $response["error"] = "Missing required fields for change_channel.";
            break;
        }

        $stmt = $conn->prepare("UPDATE channels SET diffusion_byte = ? WHERE pubid = ?");
        $stmt->bind_param("is", $diffusion_byte, $pubid);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response["message"] = "Channel updated successfully.";
        } else {
            // $response["error"] = "No channel found with pubid or update failed.";
        }
        $stmt->close();
        break;
    
    case "check_flow":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for check_flow.";
            break;
        }
        
        $stmt = $conn->prepare("SELECT pubid FROM flows WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        if (!$stmt->get_result()->num_rows > 0) {
            $response["error"] = "Flow not found.";
            $stmt->close();
            break;
        }
        $stmt->close();
    
    case "get_flow_channels":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for get_flow_channels.";
            break;
        }

        $stmt = $conn->prepare("SELECT channels FROM flows WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response["channels"] = json_decode($row["channels"]);
        } else {
            $response["error"] = "No flow found with pubid.";
        }

        $stmt->close();
        break;

    case "get_channel_info":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for get_channel_byte.";
            break;
        }

        $stmt = $conn->prepare("SELECT relpath, diffusion_byte FROM channels WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response["info"] = $row;
        } else {
            $response["error"] = "No channel found with pubid.";
        }

        $stmt->close();
        break;

    case "get_flowrate":
        $pubid = $request["pubid"] ?? null;
        if (!$pubid) {
            $response["error"] = "Missing pubid for get_flowrate.";
            break;
        }

        $stmt = $conn->prepare("SELECT rate FROM flows WHERE pubid = ?");
        $stmt->bind_param("s", $pubid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response["rate"] = (int) $row["rate"];
        } else {
            $response["error"] = "No flow found with pubid.";
        }

        $stmt->close();
        break;

    default:
        $response["error"] = "Unknown command: $command";
        break;
}

echo json_encode($response);

$conn->close();


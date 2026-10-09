import React from "react";
import { Form, Input, Button, Alert, Space, Typography, message } from "antd";
import { UserOutlined, MailOutlined, SaveOutlined } from "@ant-design/icons";
import { Link, useForm, usePage } from "@inertiajs/react";

const { Title, Text } = Typography;

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = "",
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user?.name || "",
            email: user?.email || "",
        });

    const onFinish = () => {
        patch(route("profile.update"), {
            onSuccess: () => {
                message.success("Informasi profil berhasil disimpan.");
            },
        });
    };

    return (
        <div className={className}>
            <div style={{ marginBottom: 20 }}>
                <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                    Informasi Profil
                </Title>
                <Text type="secondary" style={{ fontSize: 13 }}>
                    Perbarui nama lengkap dan alamat email akun organisasi Anda.
                </Text>
            </div>

            {recentlySuccessful && (
                <Alert
                    message="Perubahan profil Anda telah berhasil disimpan."
                    type="success"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            <Form
                layout="vertical"
                requiredMark={true}
                onFinish={onFinish}
                initialValues={{
                    name: data.name,
                    email: data.email,
                }}
            >
                <Form.Item
                    label="Nama Lengkap"
                    validateStatus={errors.name ? "error" : ""}
                    help={errors.name}
                    rules={[
                        {
                            required: true,
                            message: "Nama lengkap wajib diisi.",
                        },
                    ]}
                >
                    <Input
                        size="large"
                        prefix={<UserOutlined style={{ color: "#8c8c8c" }} />}
                        placeholder="Masukkan nama lengkap"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                    />
                </Form.Item>

                <Form.Item
                    label="Alamat Email"
                    validateStatus={errors.email ? "error" : ""}
                    help={errors.email}
                    rules={[
                        {
                            required: true,
                            message: "Alamat email wajib diisi.",
                        },
                        {
                            type: "email",
                            message: "Format alamat email tidak valid.",
                        },
                    ]}
                >
                    <Input
                        size="large"
                        type="email"
                        prefix={<MailOutlined style={{ color: "#8c8c8c" }} />}
                        placeholder="nama@organisasi.id"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                    />
                </Form.Item>

                {mustVerifyEmail && user?.email_verified_at === null && (
                    <Alert
                        type="warning"
                        showIcon
                        style={{ marginBottom: 20 }}
                        message="Verifikasi Email Diperlukan"
                        description={
                            <Space orientation="vertical" style={{ width: "100%", marginTop: 8 }}>
                                <Text style={{ fontSize: 13 }}>
                                    Alamat email Anda belum diverifikasi.
                                </Text>
                                <Link
                                    href={route("verification.send")}
                                    method="post"
                                    as="button"
                                    style={{
                                        color: "#1677ff",
                                        background: "none",
                                        border: "none",
                                        padding: 0,
                                        cursor: "pointer",
                                        textDecoration: "underline",
                                        fontWeight: 500,
                                    }}
                                >
                                    Klik di sini untuk mengirim ulang email verifikasi.
                                </Link>
                                {status === "verification-link-sent" && (
                                    <Text orientation="block" style={{ color: "#52c41a", fontWeight: 500 }}>
                                        Tautan verifikasi baru telah dikirim ke alamat email Anda.
                                    </Text>
                                )}
                            </Space>
                        }
                    />
                )}

                <Form.Item orientation="horizontal" style={{ marginBottom: 0, marginTop: 12 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        size="large"
                        icon={<SaveOutlined />}
                        loading={processing}
                    >
                        Simpan Perubahan
                    </Button>
                </Form.Item>
            </Form>
        </div>
    );
}

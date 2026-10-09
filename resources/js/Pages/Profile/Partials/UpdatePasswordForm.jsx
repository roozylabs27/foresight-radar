import React, { useRef } from "react";
import { Form, Input, Button, Alert, Typography, message } from "antd";
import { LockOutlined, SaveOutlined } from "@ant-design/icons";
import { useForm } from "@inertiajs/react";

const { Title, Text } = Typography;

export default function UpdatePasswordForm({ className = "" }) {
    const [form] = Form.useForm();
    const currentPasswordInput = useRef(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const onFinish = () => {
        put(route("password.update"), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                form.resetFields();
                message.success("Kata sandi berhasil diperbarui.");
            },
            onError: (errs) => {
                if (errs.password) {
                    reset("password", "password_confirmation");
                    form.setFieldsValue({
                        password: "",
                        password_confirmation: "",
                    });
                }
                if (errs.current_password) {
                    reset("current_password");
                    form.setFieldsValue({
                        current_password: "",
                    });
                }
            },
        });
    };

    return (
        <div className={className}>
            <div style={{ marginBottom: 20 }}>
                <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                    Perbarui Kata Sandi
                </Title>
                <Text type="secondary" style={{ fontSize: 13 }}>
                    Pastikan akun Anda menggunakan kata sandi yang kuat dan aman.
                </Text>
            </div>

            {recentlySuccessful && (
                <Alert
                    message="Kata sandi Anda telah berhasil diperbarui."
                    type="success"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            <Form
                form={form}
                layout="vertical"
                requiredMark={true}
                onFinish={onFinish}
            >
                <Form.Item
                    label="Kata Sandi Saat Ini"
                    validateStatus={errors.current_password ? "error" : ""}
                    help={errors.current_password}
                    rules={[
                        {
                            required: true,
                            message: "Kata sandi saat ini wajib diisi.",
                        },
                    ]}
                >
                    <Input.Password
                        ref={currentPasswordInput}
                        size="large"
                        prefix={<LockOutlined style={{ color: "#8c8c8c" }} />}
                        placeholder="Masukkan kata sandi saat ini"
                        value={data.current_password}
                        onChange={(e) =>
                            setData("current_password", e.target.value)
                        }
                    />
                </Form.Item>

                <Form.Item
                    label="Kata Sandi Baru"
                    validateStatus={errors.password ? "error" : ""}
                    help={errors.password}
                    rules={[
                        {
                            required: true,
                            message: "Kata sandi baru wajib diisi.",
                        },
                        {
                            min: 8,
                            message: "Kata sandi minimal 8 karakter.",
                        },
                    ]}
                >
                    <Input.Password
                        size="large"
                        prefix={<LockOutlined style={{ color: "#8c8c8c" }} />}
                        placeholder="Minimal 8 karakter"
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                    />
                </Form.Item>

                <Form.Item
                    label="Konfirmasi Kata Sandi Baru"
                    validateStatus={
                        errors.password_confirmation ? "error" : ""
                    }
                    help={errors.password_confirmation}
                    rules={[
                        {
                            required: true,
                            message: "Konfirmasi kata sandi wajib diisi.",
                        },
                        ({ getFieldValue }) => ({
                            validator(_, value) {
                                if (!value || data.password === value) {
                                    return Promise.resolve();
                                }
                                return Promise.reject(
                                    new Error("Konfirmasi kata sandi tidak cocok!")
                                );
                            },
                        }),
                    ]}
                >
                    <Input.Password
                        size="large"
                        prefix={<LockOutlined style={{ color: "#8c8c8c" }} />}
                        placeholder="Ulangi kata sandi baru"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                    />
                </Form.Item>

                <Form.Item style={{ marginBottom: 0, marginTop: 12 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        size="large"
                        icon={<SaveOutlined />}
                        loading={processing}
                    >
                        Simpan Kata Sandi
                    </Button>
                </Form.Item>
            </Form>
        </div>
    );
}
